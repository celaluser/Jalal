<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\DeliveryZone;
use Illuminate\Support\Collection;

/**
 * Delivery areas with their own fee and minimum order. A restaurant with no active zone uses the single fee and minimum
 * from its ordering settings; one with zones makes the guest pick where they are.
 */
class DeliveryZones
{
    /** @return Collection<int, DeliveryZone> */
    public function active()
    {
        return DeliveryZone::where('is_active', true)->orderBy('sort')->orderBy('id')->get();
    }

    public function enabled(): bool
    {
        return DeliveryZone::where('is_active', true)->exists();
    }

    /**
     * The zone a delivery order goes to, or null when the restaurant has no zones.
     *
     * @throws OrderException zone_required
     */
    public function resolve(mixed $id): ?DeliveryZone
    {
        if (! $this->enabled()) {
            return null;
        }

        $zone = is_numeric($id) ? DeliveryZone::where('is_active', true)->find((int) $id) : null;

        if (! $zone) {
            throw new OrderException('zone_required');
        }

        return $zone;
    }

    /**
     * Ordering settings with the zone's fee and minimum in place of the restaurant-wide ones.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function apply(array $settings, ?DeliveryZone $zone): array
    {
        return $zone ? array_replace($settings, ['delivery_fee' => (string) $zone->fee, 'delivery_min' => (string) $zone->min_order]) : $settings;
    }
}
