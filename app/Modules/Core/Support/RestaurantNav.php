<?php

namespace App\Modules\Core\Support;

/** Sidebar of the restaurant panel. Later phases register menu, orders, tables... here. */
final class RestaurantNav extends NavRegistry
{
    /** Declared up front so sections keep this order whichever module registers first. */
    protected static array $groups = ['main' => [], 'menu' => [], 'orders' => [], 'marketing' => [], 'insights' => [], 'settings' => [], 'help' => []];
}
