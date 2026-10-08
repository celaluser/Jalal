<?php

namespace App\Modules\Orders\Services;

use App\Modules\Activity\Services\ActivityLogger;
use App\Modules\Orders\Support\OrderType;
use App\Modules\Tenancy\Models\Restaurant;

/**
 * Ordering rules of one restaurant, stored as JSON on the restaurant. Money values are kept as
 * decimal strings in whole currency units (what the owner types) and read back as cents.
 */
class OrderSettings
{
    /** @var array<string, mixed> */
    public const DEFAULTS = [
        'enabled' => true,
        'paused_message' => '',
        'dine_in' => true,
        'takeaway' => true,
        'delivery' => false,
        'curbside' => false,
        'room_service' => false,
        'dine_in_pick_table' => true,
        'require_name' => false,
        'tax_rate' => '0',
        'prices_include_tax' => true,
        'service_rate' => '0',
        'delivery_fee' => '0',
        'delivery_min' => '0',
        'packaging_fee' => '0',
        'packaging_per_item' => '0',
        'schedule_orders' => false,
        'schedule_lead' => 30,
        'schedule_days' => 2,
        'max_items' => 0,
        'wait_per_order' => 0,
        'stations' => '',
        'notify_sms' => false,
        'notify_whatsapp' => false,
        'notify_push' => false,
        'alert_unaccepted' => 3,
        'escalate_minutes' => 0,
        'print_auto_kitchen' => false,
        'print_auto_receipt' => false,
        'print_width' => 48,
        'print_codepage' => 'cp437',
        'pay_cash' => true,
        'pay_card' => true,
        'auto_accept' => false,
        'prep_minutes' => 15,
        'allow_cancel' => true,
    ];

    /** @return array<string, mixed> defaults overridden by what the restaurant saved */
    public function for(Restaurant $restaurant): array
    {
        $saved = is_array($restaurant->order_settings) ? $restaurant->order_settings : [];

        return array_replace(self::DEFAULTS, array_intersect_key($saved, self::DEFAULTS));
    }

    /** @return list<string> order types the restaurant currently accepts */
    public function types(Restaurant $restaurant): array
    {
        $s = $this->for($restaurant);

        return array_values(array_filter(OrderType::ALL, fn ($t) => (bool) $s[$t]));
    }

    /** @return list<string> the restaurant's preparation stations (kitchen, bar...), empty when it does not use them */
    public function stations(Restaurant $restaurant): array
    {
        return collect(explode(',', (string) $this->for($restaurant)['stations']))->map(fn ($s) => mb_substr(trim($s), 0, 30))->filter()->unique()->take(12)->values()->all();
    }

    /** @return list<string> payment methods accepted on the spot */
    public function paymentMethods(Restaurant $restaurant): array
    {
        $s = $this->for($restaurant);

        $methods = array_keys(array_filter(['cash' => $s['pay_cash'], 'card' => $s['pay_card']]));

        // Paying online needs a gateway of the restaurant's own that is switched on and set up.
        if (app(RestaurantGateways::class)->anyReady($restaurant)) {
            $methods[] = 'online';
        }

        return $methods;
    }

    public function cents(Restaurant $restaurant, string $key): int
    {
        return (int) round((float) ($this->for($restaurant)[$key] ?? 0) * 100);
    }

    /** Whether guests can place orders right now. */
    public function accepting(Restaurant $restaurant): bool
    {
        return (bool) $this->for($restaurant)['enabled'] && $this->types($restaurant) !== [] && $this->paymentMethods($restaurant) !== [];
    }

    /** @param array<string, mixed> $input validated by rules() */
    public function save(Restaurant $restaurant, array $input): void
    {
        $clean = [];

        foreach (self::DEFAULTS as $key => $default) {
            $clean[$key] = match (true) {
                is_bool($default) => ! empty($input[$key]),
                is_int($default) => (int) ($input[$key] ?? $default),
                default => trim((string) ($input[$key] ?? $default)),
            };
        }

        $before = $this->for($restaurant);
        $restaurant->update(['order_settings' => $clean]);
        app(ActivityLogger::class)->record('updated', $restaurant, collect($clean)->filter(fn ($v, $k) => ($before[$k] ?? null) != $v)->map(fn ($v, $k) => [$before[$k] ?? null, $v])->all(), 'Ordering settings');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:99999'];

        return [
            'enabled' => ['nullable', 'boolean'], 'paused_message' => ['nullable', 'string', 'max:200'],
            'dine_in' => ['nullable', 'boolean'], 'takeaway' => ['nullable', 'boolean'], 'delivery' => ['nullable', 'boolean'], 'curbside' => ['nullable', 'boolean'], 'room_service' => ['nullable', 'boolean'],
            'dine_in_pick_table' => ['nullable', 'boolean'], 'require_name' => ['nullable', 'boolean'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'], 'prices_include_tax' => ['nullable', 'boolean'],
            'service_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'delivery_fee' => $money, 'delivery_min' => $money, 'packaging_fee' => $money, 'packaging_per_item' => $money,
            'schedule_orders' => ['nullable', 'boolean'], 'schedule_lead' => ['nullable', 'integer', 'min:0', 'max:1440'], 'schedule_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'max_items' => ['nullable', 'integer', 'min:0', 'max:500'], 'wait_per_order' => ['nullable', 'integer', 'min:0', 'max:30'],
            'stations' => ['nullable', 'string', 'max:200', 'regex:/^[\pL\pN ,&\-]*$/u'],
            'notify_sms' => ['nullable', 'boolean'], 'notify_whatsapp' => ['nullable', 'boolean'], 'notify_push' => ['nullable', 'boolean'],
            'alert_unaccepted' => ['nullable', 'integer', 'min:1', 'max:60'], 'escalate_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'print_auto_kitchen' => ['nullable', 'boolean'], 'print_auto_receipt' => ['nullable', 'boolean'], 'print_width' => ['nullable', 'in:32,42,48'], 'print_codepage' => ['nullable', 'in:cp437,cp850,cp857,cp1252,ascii'],
            'pay_cash' => ['nullable', 'boolean'], 'pay_card' => ['nullable', 'boolean'],
            'auto_accept' => ['nullable', 'boolean'], 'prep_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'allow_cancel' => ['nullable', 'boolean'],
        ];
    }
}
