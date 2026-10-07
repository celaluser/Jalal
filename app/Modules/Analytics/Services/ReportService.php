<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Marketing\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sales figures for one restaurant over a date range, in the restaurant's own time zone.
 * Cancelled orders never count as sales. Everything is computed from the orders table, so there is nothing to keep in sync.
 */
class ReportService
{
    public const MAX_DAYS = 366;

    /**
     * Turns the request's range choice into a day-aligned period in the restaurant's time zone.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable, key: string, days: int}
     */
    public function period(Restaurant $restaurant, ?string $range, ?string $from = null, ?string $to = null, int $maxDays = self::MAX_DAYS): array
    {
        $tz = $this->tz($restaurant);
        $today = CarbonImmutable::now($tz)->startOfDay();
        $key = in_array($range, ['today', 'yesterday', '7', '30', '90', 'custom'], true) ? $range : '7';

        [$start, $end] = match ($key) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            '30' => [$today->subDays(29), $today],
            '90' => [$today->subDays(89), $today],
            'custom' => $this->custom($tz, $from, $to, $today),
            default => [$today->subDays(6), $today],
        };

        // A plan without full analytics sees a short window; the request cannot widen it.
        if ($start->diffInDays($end) + 1 > $maxDays) {
            $start = $end->subDays($maxDays - 1);
            $key = $key === 'custom' ? 'custom' : (string) $maxDays;
        }

        return ['from' => $start, 'to' => $end, 'key' => $key, 'days' => (int) $start->diffInDays($end) + 1];
    }

    /** @param array{from: CarbonImmutable, to: CarbonImmutable, days: int} $period @return array<string, mixed> */
    public function build(Restaurant $restaurant, array $period, bool $full = true): array
    {
        $tz = $this->tz($restaurant);
        $current = $this->orders($restaurant, $period['from'], $period['to']);
        $previous = $this->orders($restaurant, $period['from']->subDays($period['days']), $period['from']->subDay());

        $sold = $current->where('status', '!=', OrderStatus::CANCELLED);
        $revenue = (int) $sold->sum('total_cents');
        $count = $sold->count();
        $prevSold = $previous->where('status', '!=', OrderStatus::CANCELLED);
        $prevRevenue = (int) $prevSold->sum('total_cents');
        $cancelled = $current->count() - $count;

        $daily = [];

        for ($d = $period['from']; $d <= $period['to']; $d = $d->addDay()) {
            $daily[$d->toDateString()] = ['orders' => 0, 'revenue' => 0];
        }

        foreach ($sold as $o) {
            $day = $o->local->toDateString();
            $daily[$day]['orders']++;
            $daily[$day]['revenue'] += $o->total_cents;
        }

        $report = [
            'orders' => $count, 'revenue' => $revenue, 'average' => $count ? (int) round($revenue / $count) : 0,
            'cancelled' => $cancelled, 'cancel_rate' => $current->count() ? round($cancelled / $current->count() * 100, 1) : 0.0,
            'discounts' => (int) $sold->sum('discount_cents'),
            'delta' => ['orders' => $this->delta($count, $prevSold->count()), 'revenue' => $this->delta($revenue, $prevRevenue), 'average' => $this->delta($count ? $revenue / $count : 0, $prevSold->count() ? $prevRevenue / $prevSold->count() : 0)],
            'daily' => $daily,
            'tz' => $tz,
        ];

        if (! $full) {
            return $report;
        }

        return $report + [
            'items_sold' => (int) $this->items($restaurant, $period)->sum('qty'),
            'top' => $this->items($restaurant, $period)->sortByDesc('qty')->take(10)->values()->all(),
            'heatmap' => $this->heatmap($sold),
            'by_type' => $this->breakdown($sold, 'type'),
            'by_payment' => $this->breakdown($sold, 'payment_method'),
            'by_source' => $this->breakdown($sold, 'source'),
            'customers' => $this->customers($restaurant, $period, $sold),
            'ready_minutes' => $this->readyMinutes($sold),
        ];
    }

    /** One row per order of the period, for the CSV. */
    public function orderRows(Restaurant $restaurant, array $period): \Generator
    {
        foreach ($this->query($restaurant, $period['from'], $period['to'])->orderBy('id')->cursor() as $o) {
            yield [
                $o->number, $o->created_at->setTimezone($this->tz($restaurant))->format('Y-m-d H:i'), $o->status, $o->type, $o->source, $o->payment_method, $o->paid_at ? 'yes' : 'no',
                number_format($o->subtotal_cents / 100, 2, '.', ''), number_format($o->discount_cents / 100, 2, '.', ''), number_format($o->service_cents / 100, 2, '.', ''),
                number_format($o->delivery_cents / 100, 2, '.', ''), number_format($o->tax_cents / 100, 2, '.', ''), number_format($o->total_cents / 100, 2, '.', ''), $o->promo_code,
            ];
        }
    }

    // ---- internals -----------------------------------------------------------------------

    private function tz(Restaurant $restaurant): string
    {
        return in_array($restaurant->timezone, timezone_identifiers_list(), true) ? $restaurant->timezone : 'UTC';
    }

    private function query(Restaurant $restaurant, CarbonImmutable $from, CarbonImmutable $to)
    {
        return Order::query()->where('created_at', '>=', $from->startOfDay()->utc())->where('created_at', '<=', $to->endOfDay()->utc());
    }

    /** @return Collection<int, object> light rows with a ->local time in the restaurant zone */
    private function orders(Restaurant $restaurant, CarbonImmutable $from, CarbonImmutable $to)
    {
        $tz = $this->tz($restaurant);

        return $this->query($restaurant, $from, $to)->get(['id', 'created_at', 'ready_at', 'status', 'type', 'source', 'payment_method', 'total_cents', 'discount_cents', 'customer_id'])
            ->each(fn ($o) => $o->local = $o->created_at->copy()->setTimezone($tz));
    }

    /** @return Collection<int, array{name: string, qty: int, revenue: int}> */
    private function items(Restaurant $restaurant, array $period)
    {
        return DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.restaurant_id', $restaurant->id)->where('orders.status', '!=', OrderStatus::CANCELLED)
            ->where('orders.created_at', '>=', $period['from']->startOfDay()->utc())->where('orders.created_at', '<=', $period['to']->endOfDay()->utc())
            ->groupBy('order_items.name')->selectRaw('order_items.name as name, sum(order_items.qty) as qty, sum(order_items.total_cents) as revenue')->get()
            ->map(fn ($r) => ['name' => $r->name, 'qty' => (int) $r->qty, 'revenue' => (int) $r->revenue]);
    }

    /** @return array<int, array<int, int>> orders per weekday (0 = Monday) and hour */
    private function heatmap($sold): array
    {
        $grid = array_fill(0, 7, array_fill(0, 24, 0));

        foreach ($sold as $o) {
            $grid[($o->local->dayOfWeekIso) - 1][$o->local->hour]++;
        }

        return $grid;
    }

    /** @return list<array{key: string, orders: int, revenue: int}> */
    private function breakdown($sold, string $field): array
    {
        return $sold->groupBy($field)->map(fn ($rows, $key) => ['key' => (string) $key, 'orders' => $rows->count(), 'revenue' => (int) $rows->sum('total_cents')])
            ->sortByDesc('orders')->values()->all();
    }

    /** @return array{new: int, returning: int, anonymous: int} */
    private function customers(Restaurant $restaurant, array $period, $sold): array
    {
        $ids = $sold->pluck('customer_id')->filter()->unique();
        $new = $ids->isEmpty() ? 0 : Customer::whereIn('id', $ids)->where('first_order_at', '>=', $period['from']->startOfDay()->utc())->count();

        return ['new' => $new, 'returning' => $ids->count() - $new, 'anonymous' => $sold->whereNull('customer_id')->count()];
    }

    private function readyMinutes($sold): ?int
    {
        $times = $sold->filter(fn ($o) => $o->ready_at)->map(fn ($o) => $o->created_at->diffInMinutes($o->ready_at));

        return $times->isEmpty() ? null : (int) round($times->avg());
    }

    private function delta(float|int $now, float|int $before): ?float
    {
        return $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function custom(string $tz, ?string $from, ?string $to, CarbonImmutable $today): array
    {
        try {
            $start = CarbonImmutable::parse((string) $from, $tz)->startOfDay();
            $end = CarbonImmutable::parse((string) $to, $tz)->startOfDay();
        } catch (\Throwable) {
            return [$today->subDays(6), $today];
        }

        [$start, $end] = $start <= $end ? [$start, $end] : [$end, $start];

        return [min($start, $today), min($end, $today)];
    }
}
