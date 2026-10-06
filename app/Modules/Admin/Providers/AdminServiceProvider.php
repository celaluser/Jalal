<?php

namespace App\Modules\Admin\Providers;

use App\Modules\Admin\Support\AdminNav;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'admin');

        AdminNav::add('platform', 'admin.nav.dashboard', 'admin.dashboard');
        AdminNav::add('platform', 'admin.nav.restaurants', 'admin.restaurants.index', 'admin.restaurants.*');
        AdminNav::add('billing', 'admin.nav.plans', 'admin.plans.index', 'admin.plans.*');
        AdminNav::add('billing', 'admin.nav.subscriptions', 'admin.subscriptions.index', 'admin.subscriptions.*');
        AdminNav::add('billing', 'admin.nav.invoices', 'admin.invoices.index', 'admin.invoices.*');
        AdminNav::add('billing', 'admin.nav.coupons', 'admin.coupons.index', 'admin.coupons.*');
        AdminNav::add('billing', 'admin.nav.payments', 'admin.settings.payments', 'admin.settings.payments*');
        AdminNav::add('billing', 'admin.nav.billing_settings', 'admin.settings.billing', 'admin.settings.billing*');
        AdminNav::add('system', 'admin.nav.updates', 'admin.updates.index', 'admin.updates.*');
    }
}
