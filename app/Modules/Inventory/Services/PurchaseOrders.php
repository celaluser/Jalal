<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Ingredient;
use App\Modules\Inventory\Models\Supplier;
use Illuminate\Support\Collection;

/**
 * Turns "what is running low" into purchase orders: one per supplier, with a suggested quantity that brings
 * each item back to twice its warning level (at least the warning level itself).
 */
class PurchaseOrders
{
    public function suggestedQty(Ingredient $i): float
    {
        $low = (float) $i->low_at;

        return round(max($low * 2 - (float) $i->stock_qty, $low, 0), 3);
    }

    /** @return Collection<int, array{supplier: ?Supplier, lines: list<array<string, mixed>>}> keyed by supplier id (0 = no supplier) */
    public function groups(): Collection
    {
        $suppliers = Supplier::orderBy('name')->get()->keyBy('id');

        return Ingredient::orderBy('name')->get()->filter->isLow()->groupBy(fn (Ingredient $i) => $suppliers->has($i->supplier_id) ? $i->supplier_id : 0)
            ->map(fn ($items, $sid) => ['supplier' => $suppliers[$sid] ?? null, 'lines' => $items->map(fn (Ingredient $i) => $this->line($i, $this->suggestedQty($i)))->values()->all()])
            ->sortBy(fn ($g) => $g['supplier']?->name ?? "\u{10FFFF}");
    }

    /**
     * The lines of one order as submitted: only this supplier's items, quantities cleaned.
     *
     * @param  array<int|string, mixed>  $qty  ingredient id => quantity
     * @return list<array<string, mixed>>
     */
    public function lines(int $supplierId, array $qty): array
    {
        return Ingredient::when($supplierId > 0, fn ($q) => $q->where('supplier_id', $supplierId), fn ($q) => $q->whereNull('supplier_id'))
            ->whereIn('id', array_map('intval', array_keys($qty)))->orderBy('name')->get()
            ->map(fn (Ingredient $i) => $this->line($i, round(max(0, min(9999999, (float) ($qty[$i->id] ?? 0))), 3)))
            ->filter(fn ($l) => $l['qty'] > 0)->values()->all();
    }

    /** @return array<string, mixed> */
    private function line(Ingredient $i, float $qty): array
    {
        return ['id' => $i->id, 'name' => $i->name, 'unit' => $i->unit, 'stock' => (float) $i->stock_qty, 'qty' => $qty, 'est' => $i->unit_cost !== null ? round($qty * (float) $i->unit_cost, 2) : null];
    }
}
