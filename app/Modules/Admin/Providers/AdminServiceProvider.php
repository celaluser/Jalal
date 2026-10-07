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

        $nav = [
            ['platform', 'dashboard', 'admin.dashboard', 'admin.dashboard', [], 'grid'],
            ['platform', 'restaurants', 'admin.restaurants.index', 'admin.restaurants.*', [], 'store'],
            ['platform', 'tickets', 'admin.tickets.index', 'admin.tickets.*', [], 'life-buoy'],
            ['platform', 'announcements', 'admin.announcements.index', 'admin.announcements.*', [], 'megaphone'],
            ['billing', 'plans', 'admin.plans.index', 'admin.plans.*', [], 'layers'],
            ['billing', 'subscriptions', 'admin.subscriptions.index', 'admin.subscriptions.*', [], 'repeat'],
            ['billing', 'invoices', 'admin.invoices.index', 'admin.invoices.*', [], 'receipt'],
            ['billing', 'coupons', 'admin.coupons.index', 'admin.coupons.*', [], 'percent'],
            ['billing', 'payments', 'admin.settings.payments', 'admin.settings.payments*', [], 'credit-card'],
            ['billing', 'billing_settings', 'admin.settings.billing', 'admin.settings.billing*', [], 'wallet'],
            ['content', 'landing', 'admin.landing.edit', 'admin.landing.*', [], 'layout'],
            ['content', 'blog', 'admin.posts.index', 'admin.posts.*', [], 'pen'],
            ['content', 'pages', 'admin.pages.index', 'admin.pages.*', [], 'file-text'],
            ['system', 'email_templates', 'admin.email-templates.index', 'admin.email-templates.*', [], 'mail'],
            ['system', 'settings', 'admin.settings.section', 'admin.settings.section*', ['section' => 'general'], 'sliders'],
            ['system', 'updates', 'admin.updates.index', 'admin.updates.*', [], 'refresh'],
        ];

        foreach ($nav as [$group, $key, $route, $active, $params, $icon]) {
            AdminNav::add($group, 'admin.nav.'.$key, $route, $active, $params, $icon);
        }
    }
}
