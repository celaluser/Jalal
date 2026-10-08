<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\DB;

/**
 * Menu engineering (Kasavana-Smith): every dish is placed by how often it sells (popularity) and how much each sale
 * earns (margin = price paid minus the dish's cost price).
 *
 *   star       popular, high margin   keep and promote
 *   plowhorse  popular, low margin    raise the price or trim the cost
 *   puzzle     rare, high margin      move up the menu, rename, recommend
 *   dog        rare, low margin       rework or remove
 *
 * Dishes without a cost price cannot be classified and are listed separately. The cost is the dish's own cost price:
 * extras and sizes are not costed, so margins of dishes with paid extras read slightly high.
 */
class MenuEngineering
{
    public const CLASSES = ['star', 'plowhorse', 'puzzle', 'dog'];

    /**
     * @param  array{from: \Carbon\CarbonImmutable, to: \Carbon\CarbonImmutable}  $period
     * @return array{items: list<array<string, mixed>>, unclassified: list<array<string, mixed>>, categories: list<array<string, mixed>>, food_cost_percent: ?float, thresholds: array{popularity: float, margin: int}}
     */
    public function build(Restaurant $restaurant, array $period): array
    {
        $rows = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.restaurant_id', $restaurant->id)->where('orders.status', '!=', OrderStatus::CANCELLED)
            ->where('orders.created_at', '>=', $period['from']->startOfDay()->utc())->where('orders.created_at', '<=', $period['to']->endOfDay()->utc())
            ->groupBy('order_items.product_id', 'order_items.name', 'products.cost_price', 'products.category_id', 'categories.name')
            ->selectRaw('order_items.product_id as product_id, order_items.name as name, products.cost_price as cost, products.category_id as category_id, categories.name as category_name, sum(order_items.qty) as qty, sum(order_items.total_cents) as revenue')
            ->get();

        $items = [];
        $unclassified = [];
        $costed = 0;
        $costOfCosted = 0;

        foreach ($rows as $r) {
            $qty = (int) $r->qty;
            $revenue = (int) $r->revenue;
            $row = ['name' => $r->name, 'qty' => $qty, 'revenue' => $revenue, 'category' => $this->category($restaurant, $r->category_name), 'category_id' => $r->category_id];

            if ($r->cost === null || $qty < 1) {
                $unclassified[] = $row;

                continue;
            }

            $cost = (int) round((float) $r->cost * 100);
            $row += ['unit_margin' => (int) round($revenue / $qty) - $cost, 'margin' => $revenue - $cost * $qty, 'cost' => $cost * $qty];
            $items[] = $row;
            $costed += $revenue;
            $costOfCosted += $cost * $qty;
        }

        // Popularity: a dish is popular when it sells at least 70% of what an average dish sells.
        $popularity = $items ? array_sum(array_column($items, 'qty')) / count($items) * 0.7 : 0.0;
        // Margin: compared with what an average sold portion earns across the whole menu.
        $totalQty = array_sum(array_column($items, 'qty'));
        $margin = $totalQty ? (int) round(array_sum(array_column($items, 'margin')) / $totalQty) : 0;

        foreach ($items as &$i) {
            $popular = $i['qty'] >= $popularity;
            $high = $i['unit_margin'] >= $margin;
            $i['class'] = $popular ? ($high ? 'star' : 'plowhorse') : ($high ? 'puzzle' : 'dog');
        }
        unset($i);

        usort($items, fn ($a, $b) => $b['margin'] <=> $a['margin']);

        return [
            'items' => $items, 'unclassified' => $unclassified, 'categories' => $this->categories($rows, $restaurant),
            'food_cost_percent' => $costed > 0 ? round($costOfCosted / $costed * 100, 1) : null,
            'thresholds' => ['popularity' => round($popularity, 1), 'margin' => $margin],
        ];
    }

    /** @return list<array{name: string, qty: int, revenue: int, share: float}> */
    private function categories($rows, Restaurant $restaurant): array
    {
        $total = max(1, (int) $rows->sum('revenue'));

        return $rows->groupBy(fn ($r) => $r->category_id ?? 0)->map(fn ($g) => [
            'name' => $this->category($restaurant, $g->first()->category_name), 'qty' => (int) $g->sum('qty'), 'revenue' => (int) $g->sum('revenue'),
            'share' => round($g->sum('revenue') / $total * 100, 1),
        ])->sortByDesc('revenue')->values()->all();
    }

    private function category(Restaurant $restaurant, ?string $json): string
    {
        $names = json_decode((string) $json, true);

        return is_array($names) ? (string) ($names[$restaurant->locale] ?? reset($names) ?: '') : '';
    }
}
