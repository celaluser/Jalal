<?php

namespace App\Modules\Core\Support;

/**
 * Sidebar registry shared by the super admin panel and the restaurant panel. Modules and add-ons
 * add their own entries from a service provider. Each subclass keeps its own static $groups.
 *
 *   RestaurantNav::add('menu', 'panel.nav.products', 'products.index', icon: 'store', can: 'menu.manage');
 */
abstract class NavRegistry
{
    /**
     * @var array<string, list<array{label: string, route: string, active: string, params: array<string, string>, icon: ?string, can: ?string}>>
     */
    protected static array $groups = [];

    /**
     * @param  array<string, string>  $params  route parameters when the target needs them
     * @param  string|null  $can  permission required to see the entry
     */
    public static function add(string $group, string $labelKey, string $route, ?string $active = null, array $params = [], ?string $icon = null, ?string $can = null): void
    {
        static::$groups[$group][] = ['label' => $labelKey, 'route' => $route, 'active' => $active ?? $route, 'params' => $params, 'icon' => $icon, 'can' => $can];
    }

    /**
     * @return array<string, list<array{label: string, route: string, active: string, params: array<string, string>, icon: ?string, can: ?string}>>
     */
    public static function groups(): array
    {
        return static::$groups;
    }

    public static function flush(): void
    {
        static::$groups = [];
    }
}
