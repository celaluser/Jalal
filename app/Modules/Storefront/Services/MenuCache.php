<?php

namespace App\Modules\Storefront\Services;

use App\Modules\Menu\Services\MenuService;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache of the customer menu per restaurant and language. Every menu change bumps a
 * per-restaurant version number, which makes older entries unreachable (the database cache driver
 * has no tags, so versioned keys replace them).
 */
class MenuCache
{
    public const TTL = 300;

    public function __construct(private readonly MenuService $menu) {}

    /** @return list<array<string, mixed>> */
    public function tree(Restaurant $restaurant, string $locale): array
    {
        $key = "menu.tree.{$restaurant->id}.v{$this->version($restaurant->id)}.{$locale}";

        return Cache::remember($key, self::TTL, fn () => $this->menu->tree($restaurant, $locale));
    }

    public static function bump(?int $restaurantId): void
    {
        if ($restaurantId !== null) {
            Cache::forever("menu.version.{$restaurantId}", (int) Cache::get("menu.version.{$restaurantId}", 0) + 1);
        }
    }

    private function version(int $restaurantId): int
    {
        return (int) Cache::get("menu.version.{$restaurantId}", 0);
    }
}
