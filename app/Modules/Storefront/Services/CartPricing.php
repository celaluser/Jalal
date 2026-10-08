<?php

namespace App\Modules\Storefront\Services;

use App\Modules\Branches\Services\BranchMenu;
use App\Modules\Menu\Models\OptionGroup;
use App\Modules\Menu\Models\Product;
use App\Modules\Menu\Services\MenuAvailability;
use App\Modules\Menu\Support\Schedule;
use App\Modules\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Prices a cart on the server. The browser sends only product ids, option ids, quantities and notes;
 * names, prices and availability always come from the database, so a tampered cart cannot change
 * what is charged. The order flow reuses this to build the order.
 *
 * Must run inside the restaurant's tenant context (public routes and TenantContext::runAs do that).
 */
class CartPricing
{
    public const MAX_LINES = 50;

    public const MAX_QTY = 50;

    /**
     * @param  array<int, mixed>  $lines  [{product_id, qty, options: [id...], note?}]
     * @return array{lines: list<array<string, mixed>>, subtotal_cents: int, subtotal: string, valid: bool}
     */
    public function quote(Restaurant $restaurant, array $lines, ?string $type = null, ?int $branchId = null): array
    {
        $overrides = app(BranchMenu::class)->overrides($branchId);
        $availability = app(MenuAvailability::class);
        $now = $availability->now($restaurant);
        $lines = array_slice(array_values($lines), 0, self::MAX_LINES);
        $ids = collect($lines)->map(fn ($l) => is_array($l) ? $this->id($l['product_id'] ?? null) : 0)->filter()->unique()->all();
        $products = Product::with(['category.menu', 'variants', 'comboSlots.items.dish.variants', 'optionGroups.options'])->whereIn('id', $ids)->get()->keyBy('id');
        $locale = app()->getLocale();

        $priced = [];
        $subtotal = 0;
        $valid = true;

        foreach ($lines as $i => $line) {
            $row = $this->line($restaurant, is_array($line) ? $line : [], $products, $locale, $availability, $now, $type, $overrides);
            $row['index'] = $i;
            $valid = $valid && $row['errors'] === [];
            $subtotal += $row['errors'] === [] ? $row['total_cents'] : 0;
            $priced[] = $row;
        }

        return ['lines' => $priced, 'subtotal_cents' => $subtotal, 'subtotal' => $restaurant->money($subtotal / 100), 'valid' => $valid && $priced !== []];
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<string, mixed>
     */
    private function line(Restaurant $restaurant, array $line, $products, string $locale, MenuAvailability $availability, CarbonImmutable $now, ?string $type, $overrides): array
    {
        $errors = [];
        $productId = $this->id($line['product_id'] ?? null);
        $product = $products[$productId] ?? null;
        $qty = $line['qty'] ?? 1;
        $qty = is_int($qty) || (is_string($qty) && ctype_digit($qty)) ? (int) $qty : 0;
        $note = mb_substr(trim(strip_tags((string) ($line['note'] ?? ''))), 0, 200);

        if ($qty < 1 || $qty > self::MAX_QTY) {
            $errors[] = 'quantity';
            $qty = max(1, min(self::MAX_QTY, $qty));
        }

        $base = ['product_id' => $productId, 'qty' => $qty, 'note' => $note, 'name' => '', 'options' => [], 'unit_cents' => 0, 'total_cents' => 0, 'total' => '', 'unit' => ''];

        // Off the menu right now counts as gone: hidden, outside its hours, or its limited time is over.
        if (! $product || ! $product->is_active || ! $product->category?->is_active
            || ! $availability->productOpen($product->schedule, $product->limited_until?->toDateString(), $now) || ! Schedule::isOpen($product->category->schedule, $now)
            || ($product->category->menu && (! $product->category->menu->is_active || ! Schedule::isOpen($product->category->menu->schedule, $now)))) {
            return $base + ['errors' => [...$errors, 'unavailable']];
        }

        $base['name'] = $product->tr('name', $locale, $restaurant->locale);

        if (! $availability->allowsType($product->order_types, $type)) {
            $errors[] = 'not_for_type';
        }

        // A branch can have its own price, its own sold-out switch and its own portions for a dish.
        $branch = $overrides[$product->id] ?? null;
        $stock = $branch?->stock_qty ?? ($product->tracksStock() ? $product->stock_qty : null);

        if (! $product->is_available || ($branch && $branch->is_available === false) || ($stock !== null && $stock <= 0)
            || ($product->variants->isNotEmpty() && ! $product->variants->contains('is_available', true))) {
            $errors[] = 'sold_out';
        } elseif ($stock !== null && $qty > $stock) {
            $errors[] = 'stock_limit'; // asked for more than is left
        }

        $chosen = collect(is_array($line['options'] ?? null) ? $line['options'] : [])->map(fn ($id) => $this->id($id))->filter()->unique()->values();
        $unit = (int) round((float) ($branch?->price ?? $product->price) * 100);

        // A dish with sizes is always ordered in one size, and that size sets the price.
        $variantId = null;

        if ($product->variants->isNotEmpty()) {
            $variant = $product->variants->firstWhere('id', $this->id($line['variant_id'] ?? null));

            if (! $variant) {
                $errors[] = 'variant_required';
            } elseif (! $variant->is_available) {
                $errors[] = 'sold_out';
            } else {
                $unit = (int) round((float) $variant->price * 100);
                $variantId = $variant->id;
                $base['name'] .= ' · '.$variant->tr('name', $locale, $restaurant->locale);
            }
        }
        // A set menu: one dish for every slot, each possibly with a surcharge.
        $comboProducts = [];
        $comboPicked = [];

        if ($product->is_combo && $product->comboSlots->isNotEmpty()) {
            $choices = is_array($line['combo'] ?? null) ? $line['combo'] : [];

            foreach ($product->comboSlots as $slot) {
                $item = $slot->items->firstWhere('product_id', $this->id($choices[$slot->id] ?? null));

                if (! $item) {
                    $errors[] = 'combo_required';

                    continue;
                }

                if (! $item->dish || ! $item->dish->is_active || ! $item->dish->canBeOrdered()) {
                    $errors[] = 'sold_out';

                    continue;
                }

                $delta = (int) round((float) $item->price_delta * 100);
                $unit += $delta;
                $comboProducts[] = $item->product_id;
                $comboPicked[] = ['id' => $item->product_id, 'combo_product' => $item->product_id, 'group' => $slot->tr('name', $locale, $restaurant->locale), 'name' => $item->dish->tr('name', $locale, $restaurant->locale), 'price_delta_cents' => $delta];
            }
        }

        $picked = $comboPicked;

        $knownIds = $product->optionGroups->flatMap(fn (OptionGroup $g) => $g->options->pluck('id'))->all();

        if ($chosen->diff($knownIds)->isNotEmpty()) {
            $errors[] = 'invalid_option';
        }

        foreach ($product->optionGroups as $group) {
            $selected = $group->options->whereIn('id', $chosen->all());
            $count = $selected->count();

            if ($selected->contains(fn ($o) => ! $o->is_available)) {
                $errors[] = 'option_unavailable';
            }

            if (($group->type === 'single' && $count > 1) || ($group->max_select !== null && $count > $group->max_select)) {
                $errors[] = 'too_many_options';
            }

            if ($group->is_required && $count < 1) {
                $errors[] = 'option_required';
            }

            foreach ($selected as $option) {
                $unit += (int) round((float) $option->price_delta * 100);
                $picked[] = ['id' => $option->id, 'group' => $group->tr('name', $locale, $restaurant->locale), 'name' => $option->tr('name', $locale, $restaurant->locale), 'price_delta_cents' => (int) round((float) $option->price_delta * 100)];
            }
        }

        $unit = max(0, $unit);
        $total = $unit * $qty;

        return array_merge($base, [
            'variant_id' => $variantId, 'combo_products' => $comboProducts, 'options' => $picked, 'unit_cents' => $unit, 'total_cents' => $total,
            'unit' => $restaurant->money($unit / 100), 'total' => $restaurant->money($total / 100), 'errors' => array_values(array_unique($errors)),
        ]);
    }

    /** A positive integer id from client input, or 0. Arrays, floats and junk never become an id. */
    private function id(mixed $value): int
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) ? (int) $value : 0;
    }
}
