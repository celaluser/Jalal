<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\DeliveryZone;
use App\Modules\Orders\Support\Geo;
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

    /** Zones drawn on the map. Without any, guests choose from the list as before. */
    public function hasAreas(): bool
    {
        return DeliveryZone::where('is_active', true)->whereNotNull('polygon')->exists();
    }

    /** The first active zone whose drawn area contains the point. */
    public function forPoint(float $lat, float $lng): ?DeliveryZone
    {
        return $this->active()->first(fn (DeliveryZone $z) => is_array($z->polygon) && Geo::inside($lat, $lng, $z->polygon));
    }

    public function enabled(): bool
    {
        return DeliveryZone::where('is_active', true)->exists();
    }

    /**
     * The zone a delivery order goes to, or null when the restaurant has no zones.
     *
     * With a position the drawn areas decide: a guest cannot pick a cheaper zone than the one they are in.
     *
     * @throws OrderException zone_required | outside_area
     */
    public function resolve(mixed $id, mixed $lat = null, mixed $lng = null): ?DeliveryZone
    {
        if (! $this->enabled()) {
            return null;
        }

        if (Geo::validPoint($lat, $lng)) {
            $zone = $this->forPoint((float) $lat, (float) $lng);

            if ($zone) {
                return $zone;
            }

            // Outside every drawn area: only a zone without a drawn area (a plain list entry) can still be chosen.
            $listed = is_numeric($id) ? DeliveryZone::where('is_active', true)->whereNull('polygon')->find((int) $id) : null;

            if ($listed) {
                return $listed;
            }

            throw new OrderException('outside_area');
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
