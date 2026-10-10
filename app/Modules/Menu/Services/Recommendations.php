<?php

namespace App\Modules\Menu\Services;

use App\Modules\Orders\Models\OrderItem;
use Illuminate\Support\Facades\Cache;

/**
 * "Guests who ordered this also ordered…": dishes that often share an order, worked out from the last 90 days of real orders.
 * No AI and no personal data: only dish ids and counts. A pair needs at least two orders before it counts. Cached for six hours.
 */
class Recommendations
{
    public const MIN_TOGETHER = 2;

    public const PER_DISH = 3;

    /** @return array<int, list<int>> dish id => dish ids that go with it, most often first */
    public function forRestaurant(int $restaurantId): array
    {
        return Cache::remember("recs.{$restaurantId}", 21600, fn () => $this->compute());
    }

    /** Runs inside the restaurant's tenant context. @return array<int, list<int>> */
    public function compute(): array
    {
        $orders = OrderItem::whereNotNull('product_id')->where('created_at', '>=', now()->subDays(90))->limit(40000)->get(['order_id', 'product_id'])
            ->groupBy('order_id')->map(fn ($rows) => $rows->pluck('product_id')->unique()->sort()->values()->all())->filter(fn ($ids) => count($ids) >= 2 && count($ids) <= 12);

        $pairs = [];

        foreach ($orders as $ids) {
            foreach ($ids as $i => $a) {
                foreach (array_slice($ids, $i + 1) as $b) {
                    $pairs[$a][$b] = ($pairs[$a][$b] ?? 0) + 1;
                    $pairs[$b][$a] = ($pairs[$b][$a] ?? 0) + 1;
                }
            }
        }

        $out = [];

        foreach ($pairs as $dish => $others) {
            arsort($others);
            $top = array_keys(array_filter($others, fn ($n) => $n >= self::MIN_TOGETHER));

            if ($top) {
                $out[$dish] = array_slice($top, 0, self::PER_DISH);
            }
        }

        return $out;
    }
}
