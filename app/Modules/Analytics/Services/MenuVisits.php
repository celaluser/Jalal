<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Menu\Services\MenuAvailability;
use App\Modules\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Counts how often the guest menu is opened and how often table QR codes are scanned, as simple hourly counters.
 * No cookies, no IP addresses, no visitor ids: only "someone opened it at this hour". A reload within 30 minutes in the same
 * browser session is not counted again, and obvious crawlers are ignored.
 */
class MenuVisits
{
    private const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|curl|wget|python|monitor|uptime|headless/i';

    public function __construct(private readonly MenuAvailability $availability) {}

    /** @param int $tableId 0 when the visit did not come from a table QR code */
    public function record(Restaurant $restaurant, Request $request, string $kind, int $tableId = 0): void
    {
        if ($request->isMethod('HEAD') || preg_match(self::BOTS, (string) $request->userAgent()) || ! in_array($kind, ['scan', 'view'], true)) {
            return;
        }

        // The session remembers the last count per kind and table, so a refresh is not a new visit.
        $key = "visit.{$restaurant->id}.{$kind}.{$tableId}";

        if ($request->hasSession() && $request->session()->get($key) > now()->subMinutes(30)->timestamp) {
            return;
        }

        $request->hasSession() && $request->session()->put($key, now()->timestamp);
        $this->bump($restaurant, $kind, $tableId, $this->availability->now($restaurant));
    }

    public function bump(Restaurant $restaurant, string $kind, int $tableId, \Carbon\CarbonInterface $local): void
    {
        try {
            $where = ['restaurant_id' => $restaurant->id, 'day' => $local->toDateString(), 'hour' => $local->hour, 'table_id' => $tableId, 'kind' => $kind];

            // Increment, or create the row at 1; the unique key makes two simultaneous first visits safe.
            if (DB::table('menu_visits')->where($where)->increment('count') === 0) {
                try {
                    DB::table('menu_visits')->insert($where + ['count' => 1]);
                } catch (Throwable) {
                    DB::table('menu_visits')->where($where)->increment('count');
                }
            }
        } catch (Throwable $e) {
            report($e); // counting must never break the menu
        }
    }

    /** Views this calendar month (restaurant time), for the plan limit. */
    public function monthViews(Restaurant $restaurant): int
    {
        $start = $this->availability->now($restaurant)->startOfMonth()->toDateString();

        return (int) DB::table('menu_visits')->where('restaurant_id', $restaurant->id)->where('kind', 'view')->where('day', '>=', $start)->sum('count');
    }

    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $period
     * @return array{scans: int, views: int, daily: array<string, array{scans: int, views: int}>, hours: array<int, int>, tables: list<array{table_id: int, scans: int}>}
     */
    public function report(Restaurant $restaurant, array $period): array
    {
        $rows = DB::table('menu_visits')->where('restaurant_id', $restaurant->id)->where('day', '>=', $period['from']->toDateString())->where('day', '<=', $period['to']->toDateString())->get();
        $daily = [];

        for ($d = $period['from']; $d <= $period['to']; $d = $d->addDay()) {
            $daily[$d->toDateString()] = ['scans' => 0, 'views' => 0];
        }

        $hours = array_fill(0, 24, 0);

        foreach ($rows as $r) {
            $daily[substr((string) $r->day, 0, 10)][$r->kind === 'scan' ? 'scans' : 'views'] = ($daily[substr((string) $r->day, 0, 10)][$r->kind === 'scan' ? 'scans' : 'views'] ?? 0) + $r->count;
            $hours[(int) $r->hour] += $r->count;
        }

        $tables = $rows->where('kind', 'scan')->where('table_id', '>', 0)->groupBy('table_id')->map(fn ($g, $id) => ['table_id' => (int) $id, 'scans' => (int) $g->sum('count')])->sortByDesc('scans')->values()->all();

        return ['scans' => (int) $rows->where('kind', 'scan')->sum('count'), 'views' => (int) $rows->where('kind', 'view')->sum('count'), 'daily' => $daily, 'hours' => $hours, 'tables' => $tables];
    }
}
