<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Marketing\Models\PriceRule;
use App\Modules\Menu\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Happy hour: which percentage off applies to a dish at a given moment (restaurant time). The best rule wins; rules never stack. */
class PriceRules
{
    /** @return Collection<int, PriceRule> rules running at $now */
    public function running(CarbonInterface $now): Collection
    {
        $time = $now->format('H:i');

        return PriceRule::where('is_active', true)->get()->filter(function (PriceRule $r) use ($now, $time) {
            if ($r->days && ! in_array($now->dayOfWeek, array_map('intval', $r->days), true)) {
                return false;
            }

            // A window such as 22:00-02:00 runs past midnight.
            return $r->from_time <= $r->to_time ? ($time >= $r->from_time && $time <= $r->to_time) : ($time >= $r->from_time || $time <= $r->to_time);
        })->values();
    }

    public function percentFor(Collection $running, Product $product): int
    {
        return (int) $running->filter(fn (PriceRule $r) => $r->category_id === null || $r->category_id === (int) $product->category_id)->max('percent');
    }

    public function apply(int $cents, int $percent): int
    {
        return $percent > 0 ? (int) round($cents * (100 - min(100, $percent)) / 100) : $cents;
    }
}
