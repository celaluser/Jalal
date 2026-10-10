<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Ingredient;
use App\Modules\Inventory\Models\Purchase;
use App\Modules\Inventory\Models\RecipeLine;
use App\Modules\Menu\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Stock of raw materials and what a dish costs to make. Ordering a dish uses up its ingredients, cancelling gives them back,
 * and the dish's cost price (read by the menu engineering report) follows the recipe and the latest purchase prices.
 */
class Recipes
{
    /**
     * Uses up (or, with a negative factor, gives back) the ingredients of the dishes in an order.
     *
     * @param  array<int, int|float>  $portions  product id => portions
     */
    public function consume(array $portions, int $factor = 1): void
    {
        if ($portions === []) {
            return;
        }

        foreach (RecipeLine::whereIn('product_id', array_keys($portions))->get() as $line) {
            Ingredient::whereKey($line->ingredient_id)->update(['stock_qty' => DB::raw('stock_qty - '.((float) $line->qty * $portions[$line->product_id] * $factor))]);
        }
    }

    /** Stock in from a delivery: adds to the stock and moves the unit cost towards what was just paid (weighted average). */
    public function purchase(Ingredient $ingredient, float $qty, float $unitCost, ?int $supplierId, ?string $note, ?string $date = null): Purchase
    {
        return DB::transaction(function () use ($ingredient, $qty, $unitCost, $supplierId, $note, $date) {
            $ingredient = Ingredient::whereKey($ingredient->id)->lockForUpdate()->firstOrFail();
            $onHand = max(0.0, $ingredient->stock_qty);
            $average = $onHand + $qty > 0 ? (($onHand * (float) $ingredient->unit_cost) + ($qty * $unitCost)) / ($onHand + $qty) : $unitCost;

            $ingredient->update(['stock_qty' => $ingredient->stock_qty + $qty, 'unit_cost' => round($ingredient->unit_cost === null ? $unitCost : $average, 4), 'supplier_id' => $supplierId ?: $ingredient->supplier_id]);
            $this->recost(RecipeLine::where('ingredient_id', $ingredient->id)->pluck('product_id')->all());

            return Purchase::create(['ingredient_id' => $ingredient->id, 'supplier_id' => $supplierId, 'qty' => $qty, 'unit_cost' => $unitCost, 'purchased_on' => $date ?: now()->toDateString(), 'note' => $note]);
        });
    }

    /**
     * Replaces the recipe of a dish.
     *
     * @param  array<int, float|string>  $lines  ingredient id => quantity per portion (0 or empty removes it)
     */
    public function save(Product $product, array $lines): void
    {
        DB::transaction(function () use ($product, $lines) {
            $valid = Ingredient::whereIn('id', array_keys($lines))->pluck('id')->all();
            RecipeLine::where('product_id', $product->id)->delete();

            foreach ($lines as $ingredientId => $qty) {
                if (in_array((int) $ingredientId, $valid, true) && (float) $qty > 0) {
                    RecipeLine::create(['product_id' => $product->id, 'ingredient_id' => (int) $ingredientId, 'qty' => (float) $qty]);
                }
            }

            $this->recost([$product->id]);
        });
    }

    /** What one portion costs, or null when the dish has no recipe or an ingredient has no price yet. */
    public function cost(int $productId): ?float
    {
        $lines = RecipeLine::with('ingredient')->where('product_id', $productId)->get();

        if ($lines->isEmpty() || $lines->contains(fn ($l) => $l->ingredient === null || $l->ingredient->unit_cost === null)) {
            return null;
        }

        return round($lines->sum(fn ($l) => $l->qty * $l->ingredient->unit_cost), 2);
    }

    /** @param list<int> $productIds */
    public function recost(array $productIds): void
    {
        foreach (array_unique($productIds) as $id) {
            if (($cost = $this->cost($id)) !== null) {
                Product::whereKey($id)->update(['cost_price' => $cost]);
            }
        }
    }
}
