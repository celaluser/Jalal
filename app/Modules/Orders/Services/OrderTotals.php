<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Support\OrderType;

/** Adds service charge, delivery fee and tax to an items subtotal. All values are cents. */
class OrderTotals
{
    /**
     * @param  array<string, mixed>  $settings  from OrderSettings::for()
     * @return array{subtotal: int, service: int, delivery: int, tax: int, total: int}
     */
    public function compute(int $subtotal, string $type, array $settings): array
    {
        $service = $type === OrderType::DINE_IN ? (int) round($subtotal * (float) $settings['service_rate'] / 100) : 0;
        $delivery = $type === OrderType::DELIVERY ? (int) round((float) $settings['delivery_fee'] * 100) : 0;
        $rate = (float) $settings['tax_rate'];
        $base = $subtotal + $service;

        if ($settings['prices_include_tax']) {
            // Prices already contain tax: show how much of the total it is, add nothing.
            $tax = $rate > 0 ? $base - (int) round($base / (1 + $rate / 100)) : 0;

            return ['subtotal' => $subtotal, 'service' => $service, 'delivery' => $delivery, 'tax' => $tax, 'total' => $base + $delivery];
        }

        $tax = (int) round($base * $rate / 100);

        return ['subtotal' => $subtotal, 'service' => $service, 'delivery' => $delivery, 'tax' => $tax, 'total' => $base + $tax + $delivery];
    }
}
