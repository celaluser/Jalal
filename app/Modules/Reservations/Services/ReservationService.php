<?php

namespace App\Modules\Reservations\Services;

use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Reservations\Models\Reservation;
use App\Modules\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Booking, confirming, seating and cancelling. Reasons are stable codes translated as reservations.error_{code}. */
class ReservationService
{
    public function __construct(private readonly Availability $availability, private readonly ReservationSettings $settings) {}

    /**
     * @param  array{name: string, phone?: ?string, email?: ?string, party_size: int, date: string, time: string, note?: ?string, locale?: ?string}  $data
     *
     * @throws InvalidArgumentException not_available | contact_required
     */
    public function book(Restaurant $restaurant, array $data, string $source = 'web'): Reservation
    {
        $s = $this->settings->for($restaurant);
        $tz = $this->availability->zone($restaurant);
        $staff = $source === 'staff';

        if (! $staff && empty($data['phone']) && empty($data['email'])) {
            throw new InvalidArgumentException('contact_required');
        }

        $at = CarbonImmutable::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['time'], $tz);

        // Guests can only pick from the free slots; staff may squeeze someone in at any time the table allows.
        $ok = $staff
            ? $this->availability->fits($restaurant, $at, $s['duration_minutes'], (int) $data['party_size']) || ! empty($data['force'])
            : collect($this->availability->slots($restaurant, $data['date'], (int) $data['party_size']))->contains(fn ($t) => $t->equalTo($at));

        if (! $ok) {
            throw new InvalidArgumentException('not_available');
        }

        $res = new Reservation([
            'name' => mb_substr(trim(strip_tags($data['name'])), 0, 80), 'phone' => $data['phone'] ?? null, 'email' => isset($data['email']) ? mb_strtolower($data['email']) : null,
            'party_size' => (int) $data['party_size'], 'starts_at' => $at->utc(), 'duration_minutes' => $s['duration_minutes'],
            'status' => $s['auto_confirm'] || $staff ? 'confirmed' : 'pending', 'source' => $source,
            'note' => isset($data['note']) ? mb_substr(trim(strip_tags($data['note'])), 0, 300) ?: null : null, 'locale' => $data['locale'] ?? app()->getLocale(),
        ]);
        $res->forceFill(['token' => Str::lower(Str::random(24))])->save();

        if ($res->status === 'confirmed') {
            $res->update(['table_id' => $this->availability->pickTable($restaurant, $res)?->id]);
        }

        $this->mail($restaurant, $res, $res->status === 'confirmed' ? 'reservation_confirmed' : 'reservation_received');

        return $res;
    }

    public function confirm(Restaurant $restaurant, Reservation $res, ?int $tableId = null): Reservation
    {
        if (! in_array($res->status, ['pending'], true)) {
            throw new InvalidArgumentException('invalid_transition');
        }

        $res->update(['status' => 'confirmed', 'table_id' => $tableId ?? $res->table_id ?? $this->availability->pickTable($restaurant, $res)?->id]);
        $this->mail($restaurant, $res, 'reservation_confirmed');

        return $res;
    }

    public function setStatus(Restaurant $restaurant, Reservation $res, string $to): Reservation
    {
        $allowed = ['pending' => ['confirmed', 'cancelled'], 'confirmed' => ['seated', 'cancelled', 'no_show'], 'seated' => ['completed']][$res->status] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new InvalidArgumentException('invalid_transition');
        }

        if ($to === 'confirmed') {
            return $this->confirm($restaurant, $res);
        }

        $res->update(['status' => $to]);

        if ($to === 'cancelled') {
            $this->mail($restaurant, $res, 'reservation_cancelled');
        }

        return $res;
    }

    /** A guest cancels from their own link, up to the start. */
    public function cancelByGuest(Restaurant $restaurant, Reservation $res): Reservation
    {
        if (! in_array($res->status, ['pending', 'confirmed'], true) || $res->starts_at->isPast()) {
            throw new InvalidArgumentException('cannot_cancel');
        }

        return $this->setStatus($restaurant, $res, 'cancelled');
    }

    public function mail(Restaurant $restaurant, Reservation $res, string $template): void
    {
        if (! $res->email) {
            return;
        }

        $tz = $this->availability->zone($restaurant);
        SafeMail::send($res->email, new TemplatedMail($template, [
            'name' => $res->name, 'restaurant' => $restaurant->name, 'when' => $res->starts_at->setTimezone($tz)->isoFormat('dddd D MMMM, HH:mm'),
            'party' => (string) $res->party_size, 'manage_url' => $restaurant->publicUrl('reserve/'.$res->token),
        ], $res->locale));
    }
}
