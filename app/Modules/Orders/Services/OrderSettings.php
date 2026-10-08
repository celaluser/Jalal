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
        'dine_in_pick_table' => true,
        'require_name' => false,
        'tax_rate' => '0',
        'prices_include_tax' => true,
        'service_rate' => '0',
        'delivery_fee' => '0',
        'delivery_min' => '0',
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

    /** @return list<string> payment methods accepted on the spot */
    public function paymentMethods(Restaurant $restaurant): array
    {
        $s = $this->for($restaurant);

        return array_keys(array_filter(['cash' => $s['pay_cash'], 'card' => $s['pay_card']]));
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
            'dine_in' => ['nullable', 'boolean'], 'takeaway' => ['nullable', 'boolean'], 'delivery' => ['nullable', 'boolean'],
            'dine_in_pick_table' => ['nullable', 'boolean'], 'require_name' => ['nullable', 'boolean'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'], 'prices_include_tax' => ['nullable', 'boolean'],
            'service_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'delivery_fee' => $money, 'delivery_min' => $money,
            'pay_cash' => ['nullable', 'boolean'], 'pay_card' => ['nullable', 'boolean'],
            'auto_accept' => ['nullable', 'boolean'], 'prep_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'allow_cancel' => ['nullable', 'boolean'],
        ];
    }
}
