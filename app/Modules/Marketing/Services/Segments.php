<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Database\Eloquent\Builder;

/** Guest groups for campaigns, and the loyalty tier a guest has reached (by number of orders). */
class Segments
{
    public const ALL = ['all', 'new', 'regulars', 'vip', 'lapsed'];

    public function __construct(private readonly MarketingSettings $settings) {}

    public function scope(Builder $query, string $segment, Restaurant $restaurant): Builder
    {
        $s = $this->settings->for($restaurant);

        return match ($segment) {
            'new' => $query->where('orders_count', '<=', 1),
            'regulars' => $query->where('orders_count', '>=', (int) $s['tier_silver']),
            'vip' => $query->where('orders_count', '>=', (int) $s['tier_gold']),
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
