<?php

namespace App\Modules\Reservations\Services;

use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Models\Restaurant;

/** How a restaurant takes bookings. Stored as one JSON setting per restaurant. */
class ReservationSettings
{
    public const DEFAULTS = [
        'enabled' => false, 'auto_confirm' => false,
        'open_from' => '12:00', 'open_to' => '22:00', 'days' => [],   // days 1-7 (Monday = 1); empty = every day
        'slot_minutes' => 30, 'duration_minutes' => 90, 'max_party' => 10, 'lead_minutes' => 60, 'days_ahead' => 30,
        'max_covers' => 40,        // used when the restaurant has no tables set up: seats it can fill at once
        'deposit_per_person' => 0, // money per guest; 0 = no deposit. Needs an online payment gateway (plan feature online_payments)
        'deposit_hold_minutes' => 15, // an unpaid booking frees its table after this long
        'deposit_refund_hours' => 24, // cancel at least this long before and the deposit is paid back; later, or no-show: the restaurant keeps it
        'remind_hours' => 2,       // reminder e-mail this long before; 0 = off
    ];

    public function __construct(private readonly SettingsService $store, private readonly LimitGuard $limits) {}

    /** @return array<string, mixed> */
    public function for(Restaurant $restaurant): array
    {
        $saved = json_decode((string) $this->store->get('reservations.config', '{}', $restaurant->id), true);

        return array_replace(self::DEFAULTS, array_intersect_key(is_array($saved) ? $saved : [], self::DEFAULTS));
    }

    /**
     * The deposit a booking of $party guests needs, in minor units; 0 when the restaurant asks for none or cannot take online payments.
     * Money is rounded per guest first, so the total is always a whole multiple of the per-guest amount.
     */
    public function depositCents(Restaurant $restaurant, int $party): int
    {
        $per = (int) round((float) $this->for($restaurant)['deposit_per_person'] * 100);

        return $per > 0 && $party > 0 && app(\App\Modules\Orders\Services\RestaurantGateways::class)->availableFor($restaurant) !== [] ? $per * $party : 0;
    }

    /** On, and the plan includes reservations. */
    public function active(Restaurant $restaurant): bool
    {
        return (bool) $this->for($restaurant)['enabled'] && $this->limits->hasFeature($restaurant, 'reservations');
    }

    /** @param array<string, mixed> $input validated by rules() */
    public function save(Restaurant $restaurant, array $input): void
    {
        $clean = [];

        foreach (self::DEFAULTS as $key => $default) {
            $clean[$key] = match (true) {
                is_bool($default) => ! empty($input[$key]),
                is_float($default) || $key === 'deposit_per_person' => max(0, round((float) ($input[$key] ?? $default), 2)),
                is_int($default) => (int) ($input[$key] ?? $default),
                is_array($default) => array_values(array_unique(array_map('intval', (array) ($input[$key] ?? [])))),
                default => (string) ($input[$key] ?? $default),
            };
        }

        $this->store->set('reservations.config', json_encode($clean), $restaurant->id);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'enabled' => ['nullable', 'boolean'], 'auto_confirm' => ['nullable', 'boolean'],
            'open_from' => ['required', 'date_format:H:i'], 'open_to' => ['required', 'date_format:H:i', 'after:open_from'],
            'days' => ['nullable', 'array'], 'days.*' => ['integer', 'between:1,7'],
            'slot_minutes' => ['required', 'in:15,30,60'], 'duration_minutes' => ['required', 'integer', 'min:30', 'max:480'],
            'max_party' => ['required', 'integer', 'min:1', 'max:100'], 'lead_minutes' => ['required', 'integer', 'min:0', 'max:2880'],
            'days_ahead' => ['required', 'integer', 'min:1', 'max:365'], 'max_covers' => ['required', 'integer', 'min:1', 'max:5000'], 'deposit_per_person' => ['nullable', 'numeric', 'min:0', 'max:100000'], 'deposit_hold_minutes' => ['nullable', 'integer', 'between:5,120'], 'deposit_refund_hours' => ['nullable', 'integer', 'between:0,720'],
            'remind_hours' => ['required', 'integer', 'min:0', 'max:48'],
        ];
    }
}
