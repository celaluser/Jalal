<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Marketing\Models\CustomerSegment;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Database\Eloquent\Builder;

/** Guest groups for campaigns, and the loyalty tier a guest has reached (by number of orders). */
class Segments
{
    public const ALL = ['all', 'new', 'regulars', 'vip', 'lapsed', 'birthday'];

    public function __construct(private readonly MarketingSettings $settings) {}

    public function scope(Builder $query, string $segment, Restaurant $restaurant): Builder
    {
        $s = $this->settings->for($restaurant);

        // A segment the owner saved: custom:<id>. An id that is gone (or belongs to another restaurant) reaches nobody.
        if (preg_match('/^custom:(\d{1,6})$/', $segment, $m)) {
            $saved = CustomerSegment::find((int) $m[1]);

            return $saved ? app(SegmentRules::class)->apply($query, (array) $saved->rules, $restaurant) : $query->whereRaw('1 = 0');
        }

        return match ($segment) {
            'new' => $query->where('orders_count', '<=', 1),
            'regulars' => $query->where('orders_count', '>=', (int) $s['tier_silver']),
            'vip' => $query->where('orders_count', '>=', (int) $s['tier_gold']),
            'birthday' => $query->where('birth_month', (int) now()->month), // everyone with a birthday this month
            'lapsed' => $query->where('last_order_at', '<', now()->subDays((int) $s['autopilot_days'])),
            default => $query,
        };
    }

    /** bronze | silver | gold */
    public function tier(int $orders, Restaurant $restaurant): string
    {
        $s = $this->settings->for($restaurant);

        return $orders >= (int) $s['tier_gold'] ? 'gold' : ($orders >= (int) $s['tier_silver'] ? 'silver' : 'bronze');
    }
}
