<?php

namespace App\Modules\Analytics\Services;

use App\Models\User;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * How the floor runs: what each team member did with orders (from the order history) and how fast tables turn over.
 * Staff figures count only actions a signed-in person took; automatic steps (auto-accept, guest actions) have no person and are left out.
 */
class OperationsReport
{
    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $period
     * @return array{staff: list<array<string, mixed>>, tables: list<array<string, mixed>>}
     */
    public function build(Restaurant $restaurant, array $period): array
    {
        $from = $period['from']->startOfDay()->utc();
        $to = $period['to']->endOfDay()->utc();

        return ['staff' => $this->staff($restaurant, $from, $to), 'tables' => $this->tables($restaurant, $from, $to)];
    }

    /** @return list<array<string, mixed>> */
    private function staff(Restaurant $restaurant, $from, $to): array
    {
        $events = DB::table('order_events')->join('orders', 'orders.id', '=', 'order_events.order_id')
            ->where('order_events.restaurant_id', $restaurant->id)->where('order_events.type', 'status')->whereNotNull('order_events.user_id')
            ->where('order_events.created_at', '>=', $from)->where('order_events.created_at', '<=', $to)
            ->get(['order_events.user_id', 'order_events.to', 'order_events.created_at as at', 'orders.created_at as placed']);

        $names = User::whereIn('id', $events->pluck('user_id')->unique())->pluck('name', 'id');

        return $events->groupBy('user_id')->map(function ($rows, $id) use ($names) {
            $accepted = $rows->where('to', OrderStatus::ACCEPTED);
            $waits = $accepted->map(fn ($r) => max(0, strtotime($r->at) - strtotime($r->placed)));

            return [
                'name' => $names[$id] ?? '#'.$id, 'actions' => $rows->count(), 'accepted' => $accepted->count(), 'ready' => $rows->where('to', OrderStatus::READY)->count(),
                'completed' => $rows->where('to', OrderStatus::COMPLETED)->count(), 'cancelled' => $rows->where('to', OrderStatus::CANCELLED)->count(),
                'avg_accept_seconds' => $waits->isEmpty() ? null : (int) round($waits->avg()),
            ];
        })->sortByDesc('actions')->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function tables(Restaurant $restaurant, $from, $to): array
    {
        $rows = DB::table('orders')->where('restaurant_id', $restaurant->id)->where('type', 'dine_in')->whereNotNull('table_name')
            ->where('status', OrderStatus::COMPLETED)->where('created_at', '>=', $from)->where('created_at', '<=', $to)
            ->get(['table_name', 'total_cents', 'created_at', 'completed_at']);

        return $rows->groupBy('table_name')->map(function ($g, $name) {
            $minutes = $g->filter(fn ($o) => $o->completed_at)->map(fn ($o) => (strtotime($o->completed_at) - strtotime($o->created_at)) / 60)->filter(fn ($m) => $m >= 0 && $m <= 600);

            return ['table' => (string) $name, 'orders' => $g->count(), 'revenue' => (int) $g->sum('total_cents'), 'average' => (int) round($g->avg('total_cents')), 'avg_minutes' => $minutes->isEmpty() ? null : (int) round($minutes->avg())];
        })->sortByDesc('orders')->values()->all();
    }
}
