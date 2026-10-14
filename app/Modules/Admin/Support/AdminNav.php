<?php

namespace App\Modules\Admin\Support;

use App\Modules\Core\Support\NavRegistry;

/** Sidebar of the super admin panel. */
final class AdminNav extends NavRegistry
{
    protected static array $groups = [];

    protected static array $essential = ['admin.dashboard', 'admin.restaurants.index', 'admin.tickets.index', 'admin.plans.index', 'admin.subscriptions.index', 'admin.invoices.index', 'admin.settings.payments', 'admin.settings.section', 'admin.system.index'];
}
