<?php

namespace App\Modules\Admin\Support;

/**
 * Sidebar registry of the super admin panel. Modules and add-ons register their own entries
 * from a service provider:  AdminNav::add('billing', 'admin.nav.plans', 'admin.plans.index');
 */
final class AdminNav
{
    /** @var array<string, list<array{label: string, route: string, active: string, params: array<string, string>}>> */
    private static array $groups = [];

    public static function add(string $group, string $labelKey, string $route, ?string $activePattern = null, array $params = []): void
    {
        self::$groups[$group][] = ['label' => $labelKey, 'route' => $route, 'active' => $activePattern ?? $route, 'params' => $params];
    }

    /**
     * @return array<string, list<array{label: string, route: string, active: string, params: array<string, string>}>>
     */
    public static function groups(): array
    {
        return self::$groups;
    }

    public static function flush(): void
    {
        self::$groups = [];
    }
}
