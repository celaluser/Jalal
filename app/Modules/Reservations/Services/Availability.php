<?php

namespace App\Modules\Reservations\Services;

use App\Modules\Reservations\Models\Reservation;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;

/**
 * Which times can still be booked for a party. With tables set up, a slot is free when a table that fits the party is not
 * taken by an overlapping booking (bookings without a table use up one fitting table each). Without tables the restaurant's
 * "seats at once" figure is the limit.
 */
class Availability
{
    public function __construct(private readonly ReservationSettings $settings) {}

    public function zone(Restaurant $restaurant): string
    {
        return in_array($restaurant->timezone, timezone_identifiers_list(), true) ? $restaurant->timezone : 'UTC';
    }

    /** @return list<CarbonImmutable> start times (restaurant time zone) that can be booked on the given day */
    public function slots(Restaurant $restaurant, string $date, int $party, ?int $ignoreId = null): array
    {
        $s = $this->settings->for($restaurant);
        $tz = $this->zone($restaurant);

        try {
            $day = CarbonImmutable::createFromFormat('Y-m-d', $date, $tz)->startOfDay();
        } catch (\Throwable) {
            return [];
        }

        $now = CarbonImmutable::now($tz);

        if ($party < 1 || $party > $s['max_party'] || $day->lt($now->startOfDay()) || $day->gt($now->addDays($s['days_ahead'])->endOfDay())
            || ($s['days'] !== [] && ! in_array($day->dayOfWeekIso, $s['days'], true))) {
            return [];
        }

        [$fh, $fm] = array_map('intval', explode(':', $s['open_from']));
        [$th, $tm] = array_map('intval', explode(':', $s['open_to']));
        $start = $day->setTime($fh, $fm);
        $lastStart = $day->setTime($th, $tm)->subMinutes($s['duration_minutes']);
        $earliest = $now->addMinutes($s['lead_minutes']);
        $out = [];

        for ($t = $start; $t->lte($lastStart); $t = $t->addMinutes($s['slot_minutes'])) {
            if ($t->gte($earliest) && $this->fits($restaurant, $t, $s['duration_minutes'], $party, $ignoreId)) {
                $out[] = $t;
            }
        }

        return $out;
    }

    public function fits(Restaurant $restaurant, CarbonImmutable $at, int $minutes, int $party, ?int $ignoreId = null): bool
    {
        $s = $this->settings->for($restaurant);
        $from = $at->utc();
        $to = $from->addMinutes($minutes);
        $overlap = Reservation::whereIn('status', Reservation::ACTIVE)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('starts_at', '<', $to)->get()->filter(fn (Reservation $r) => $r->endsAt()->gt($from));

        $tables = DiningTable::where('is_active', true)->get();

        if ($tables->isEmpty()) {
            return $overlap->sum('party_size') + $party <= $s['max_covers'];
        }

        $suitable = $tables->filter(fn ($t) => ($t->seats ?? 4) >= $party);
        $taken = $overlap->filter(fn ($r) => $r->table_id && $suitable->contains('id', $r->table_id))->pluck('table_id')->unique()->count()
            + $overlap->whereNull('table_id')->count(); // a booking without a table still needs one

        return $suitable->count() > $taken;
    }

    /** A table that fits the party and is free for the period, smallest first, or null. */
    public function pickTable(Restaurant $restaurant, Reservation $res): ?DiningTable
    {
        $from = $res->starts_at->copy()->utc();
        $to = $from->copy()->addMinutes($res->duration_minutes);
        $busy = Reservation::whereIn('status', Reservation::ACTIVE)->where('id', '!=', $res->id)->whereNotNull('table_id')->where('starts_at', '<', $to)->get()
            ->filter(fn (Reservation $r) => $r->endsAt()->gt($from))->pluck('table_id');

        return DiningTable::where('is_active', true)->whereNotIn('id', $busy)->get()->filter(fn ($t) => ($t->seats ?? 4) >= $res->party_size)->sortBy(fn ($t) => $t->seats ?? 4)->first();
    }
}
