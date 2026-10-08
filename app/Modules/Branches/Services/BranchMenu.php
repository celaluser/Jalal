<?php

namespace App\Modules\Branches\Services;

use App\Modules\Branches\Models\Branch;
use App\Modules\Branches\Models\BranchProduct;
use App\Modules\Menu\Models\Product;
use Illuminate\Support\Collection;

/** What a branch changes about the shared menu: its own price, availability and portions for a dish. */
class BranchMenu
{
    /** @return Collection<int, BranchProduct> overrides of one branch keyed by product id */
    public function overrides(?int $branchId): Collection
    {
        return $branchId ? BranchProduct::where('branch_id', $branchId)->get()->keyBy('product_id') : collect();
    }

    /**
     * Applies a branch's overrides to the cached menu tree.
     *
     * @param  list<array<string, mixed>>  $tree
     * @return list<array<string, mixed>>
     */
    public function apply(array $tree, ?int $branchId): array
    {
        if (! $branchId) {
            return $tree;
        }

        $over = $this->overrides($branchId);

        foreach ($tree as &$category) {
            foreach ($category['products'] as &$p) {
                $o = $over[$p['id']] ?? null;

                if (! $o) {
                    continue;
                }

                if ($o->price !== null && empty($p['variants'])) {
                    $p['price'] = (float) $o->price;
                }

                // Sold out here, or this branch's own stock ran out.
                if ($o->is_available === false || ($o->stock_qty !== null && $o->stock_qty <= 0)) {
                    $p['available'] = false;
                }
            }
            unset($p);
        }

        return $tree;
    }

    /** Does this branch's own stock (when it keeps one) cover the quantity? Null: no branch stock, use the shared one. */
    public function stockLeft(?int $branchId, Product $product): ?int
    {
        $row = $branchId ? BranchProduct::where('branch_id', $branchId)->where('product_id', $product->id)->first() : null;

        return $row?->stock_qty;
    }
}
