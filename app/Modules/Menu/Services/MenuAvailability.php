<?php

namespace App\Modules\Menu\Services;

use App\Modules\Menu\Support\Schedule;
use App\Modules\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;

/**
 * What is on the menu right now. The cached menu holds everything with its rules; this applies them at the moment of
 * viewing or ordering, in the restaurant's own time zone: dish and category schedules, "limited time" end dates.
 */
class MenuAvailability
{
    public function now(Restaurant $restaurant): CarbonImmutable
    {
        $tz = in_array($restaurant->timezone, timezone_identifiers_list(), true) ? $restaurant->timezone : 'UTC';

        return CarbonImmutable::now($tz);
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     * @return list<array<string, mixed>>
     */
    public function filter(array $tree, Restaurant $restaurant): array
    {
        $now = $this->now($restaurant);
        $out = [];

        foreach ($tree as $category) {
            $menu = $category['menu_open'] ?? null;

            if (! Schedule::isOpen($category['schedule'] ?? null, $now) || ($menu !== null && (! $menu['active'] || ! Schedule::isOpen($menu['schedule'], $now)))) {
                continue;
            }

            $category['products'] = array_values(array_filter($category['products'], fn ($p) => $this->productOpen($p['schedule'] ?? null, $p['limited_until'] ?? null, $now)));

            if ($category['products'] !== []) {
                $out[] = $category;
            }
        }

        return $out;
    }

    /**
     * The menus a guest can switch between right now (only when there is more than one), in menu order.
     *
     * @param  list<array<string, mixed>>  $tree  already filtered
     * @return list<array{id: int, name: string}>
     */
    public function menusOf(array $tree, string $mainLabel): array
    {
        $seen = [];

        foreach ($tree as $c) {
            $seen[$c['menu_id'] ?? 0] ??= ['id' => (int) ($c['menu_id'] ?? 0), 'name' => ($c['menu_id'] ?? 0) ? (string) $c['menu_name'] : $mainLabel];
        }

        return count($seen) > 1 ? array_values($seen) : [];
    }

    /** @param array<string, mixed>|null $schedule */
    public function productOpen(?array $schedule, ?string $limitedUntil, CarbonImmutable $now): bool
    {
        if ($limitedUntil !== null && $now->toDateString() > $limitedUntil) {
            return false; // the limited offer is over
        }

        return Schedule::isOpen($schedule, $now);
    }

    /** @param list<string>|null $types null = every order type */
    public function allowsType(?array $types, ?string $type): bool
    {
        return $types === null || $types === [] || $type === null || in_array($type, $types, true);
    }
}
