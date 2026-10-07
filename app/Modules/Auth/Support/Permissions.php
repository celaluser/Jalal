<?php

namespace App\Modules\Auth\Support;

/**
 * Permission catalogue and the default permission set of each built-in role.
 * Later phases append their permissions here; RolesAndPermissionsSeeder syncs them.
 */
final class Permissions
{
    public const SUPER_ADMIN = 'super_admin';

    public const OWNER = 'restaurant_owner';

    public const MANAGER = 'manager';

    public const WAITER = 'waiter';

    public const KITCHEN = 'kitchen';

    public const CASHIER = 'cashier';

    /** @var list<string> */
    public const RESTAURANT_ROLES = [self::OWNER, self::MANAGER, self::WAITER, self::KITCHEN, self::CASHIER];

    /**
     * @return array<string, list<string>> role => permissions ('*' = every restaurant permission)
     */
    public static function defaults(): array
    {
        return [
            self::OWNER => ['*'],
            self::MANAGER => [
                'menu.manage', 'orders.view', 'orders.manage', 'tables.manage', 'staff.view',
                'reports.view', 'customers.view', 'marketing.manage', 'settings.view', 'support.manage',
            ],
            self::WAITER => ['orders.view', 'orders.create', 'tables.view'],
            self::KITCHEN => ['orders.view', 'kitchen.view'],
            self::CASHIER => ['orders.view', 'orders.manage', 'payments.manage', 'tables.view'],
        ];
    }

    /**
     * @return list<string> every restaurant-level permission
     */
    public static function restaurant(): array
    {
        return [
            'menu.manage', 'orders.view', 'orders.create', 'orders.manage', 'tables.view', 'tables.manage',
            'staff.view', 'staff.manage', 'roles.manage', 'reports.view', 'customers.view',
            'marketing.manage', 'settings.view', 'settings.manage', 'payments.manage', 'kitchen.view',
            'billing.manage', 'support.manage',
        ];
    }

    /**
     * @return list<string> platform-level permissions (super admin only)
     */
    public static function platform(): array
    {
        return ['platform.manage'];
    }
}
