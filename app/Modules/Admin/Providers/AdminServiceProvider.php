<?php

namespace App\Modules\Admin\Providers;

use App\Modules\Admin\Services\SystemInfo;
use App\Modules\Admin\Support\AdminNav;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'admin');

        // A processed job proves the queue worker is alive; the System page reads this heartbeat.
        Event::listen(JobProcessed::class, fn () => SystemInfo::beat(SystemInfo::QUEUE_KEY));

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
            ['system', 'languages', 'admin.languages.index', 'admin.languages.*', [], 'globe'],
            ['system', 'currencies', 'admin.currencies.index', 'admin.currencies.*', [], 'wallet'],
            ['system', 'translations', 'admin.translations.index', 'admin.translations.*', [], 'pen'],
            ['system', 'email_templates', 'admin.email-templates.index', 'admin.email-templates.*', [], 'mail'],
            ['system', 'settings', 'admin.settings.section', 'admin.settings.section*', ['section' => 'general'], 'sliders'],
            ['system', 'system_status', 'admin.system.index', 'admin.system.index', [], 'activity'],
            ['system', 'logs', 'admin.system.logs', 'admin.system.logs*', [], 'file-text'],
            ['system', 'backups', 'admin.system.backups', 'admin.system.backups*', [], 'download'],
            ['system', 'updates', 'admin.updates.index', 'admin.updates.*', [], 'refresh'],
        ];

        foreach ($nav as [$group, $key, $route, $active, $params, $icon]) {
            AdminNav::add($group, 'admin.nav.'.$key, $route, $active, $params, $icon);
        }
    }
}
