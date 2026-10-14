<?php

namespace App\Modules\Core\Support;

/** Sidebar of the restaurant panel. Later phases register menu, orders, tables... here. */
final class RestaurantNav extends NavRegistry
{
    /** Declared up front so sections keep this order whichever module registers first. */
    protected static array $groups = ['main' => [], 'menu' => [], 'orders' => [], 'marketing' => [], 'insights' => [], 'settings' => [], 'help' => []];

    protected static array $essential = ['dashboard', 'orders.board', 'orders.pos.index', 'menu.index', 'tables.index', 'reports.index', 'appearance.edit', 'restaurant.settings', 'billing.index', 'support.index'];
}
