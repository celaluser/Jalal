<?php

namespace App\Modules\Tables\Services;

use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\DB;

/** Table rules: plan limit, bulk creation and finding a table from the token in a scanned QR code. */
class TableService
{
    public const BULK_MAX = 100;

    public function __construct(private readonly LimitGuard $limits) {}

    /** How many more tables the plan allows: null = unlimited. */
    public function remaining(Restaurant $restaurant): ?int
    {
        return $this->limits->remaining($restaurant, 'tables', DiningTable::count());
    }

    /**
     * Create "{prefix} {from}" ... "{prefix} {to}", skipping names that already exist.
     *
     * @return array{created: int, skipped: int}
     */
    public function bulkCreate(string $prefix, int $from, int $to, ?int $areaId, ?int $seats): array
    {
        $names = collect(range($from, $to))->map(fn (int $n) => trim("{$prefix} {$n}"));
        $existing = DiningTable::whereIn('name', $names)->pluck('name')->all();
        $fresh = $names->reject(fn ($n) => in_array($n, $existing, true))->values();
        $sort = (int) DiningTable::max('sort');

        DB::transaction(function () use ($fresh, $areaId, $seats, $sort) {
            foreach ($fresh as $i => $name) {
                DiningTable::create(['name' => $name, 'area_id' => $areaId, 'seats' => $seats, 'sort' => $sort + $i + 1]);
            }
        });

        return ['created' => $fresh->count(), 'skipped' => $names->count() - $fresh->count()];
    }

    /** The active table behind a scanned token, within the current restaurant. */
    public function findByToken(string $token): ?DiningTable
    {
        return DiningTable::where('token', $token)->where('is_active', true)->first();
    }
}
