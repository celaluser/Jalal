<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Support\OrderType;

/** Adds service charge, delivery fee and tax to an items subtotal. All values are cents. */
class OrderTotals
{
    /**
     * @param  array<string, mixed>  $settings  from OrderSettings::for()
     * @param  int  $discount  promo code discount, taken off the items before service charge and tax
     * @param  int  $itemCount  portions in the order, for a per-item packaging fee
     * @return array{subtotal: int, discount: int, service: int, delivery: int, packaging: int, tax: int, total: int}
     */
    public function compute(int $subtotal, string $type, array $settings, int $discount = 0, int $itemCount = 0): array
    {
        $discount = max(0, min($discount, $subtotal));
        $items = $subtotal - $discount;
        $service = in_array($type, [OrderType::DINE_IN, OrderType::ROOM_SERVICE], true) ? (int) round($items * (float) $settings['service_rate'] / 100) : 0;
        $delivery = $type === OrderType::DELIVERY ? (int) round((float) $settings['delivery_fee'] * 100) : 0;
        $packaging = in_array($type, OrderType::PACKED, true) ? (int) round((float) ($settings['packaging_fee'] ?? 0) * 100) + (int) round((float) ($settings['packaging_per_item'] ?? 0) * 100) * $itemCount : 0;
        $rate = (float) $settings['tax_rate'];
        $base = $items + $service + $packaging;

        if ($settings['prices_include_tax']) {
            // Prices already contain tax: show how much of the total it is, add nothing.
            $tax = $rate > 0 ? $base - (int) round($base / (1 + $rate / 100)) : 0;

            return ['subtotal' => $subtotal, 'discount' => $discount, 'service' => $service, 'delivery' => $delivery, 'packaging' => $packaging, 'tax' => $tax, 'total' => $base + $delivery];
        }

        $tax = (int) round($base * $rate / 100);

        return ['subtotal' => $subtotal, 'discount' => $discount, 'service' => $service, 'delivery' => $delivery, 'packaging' => $packaging, 'tax' => $tax, 'total' => $base + $tax + $delivery];
    }
}
