<?php

namespace App\Modules\Analytics\Providers;

use App\Modules\Analytics\Console\WeeklyDigest;
use App\Modules\Core\Mail\EmailTemplateRegistry;
use App\Modules\Core\Support\RestaurantNav;
use Illuminate\Support\ServiceProvider;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        \App\Modules\Billing\Support\UsageRegistry::register('scans_per_month', fn (\App\Modules\Tenancy\Models\Restaurant $r) => app(\App\Modules\Analytics\Services\MenuVisits::class)->monthViews($r));
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'analytics');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([WeeklyDigest::class]);
        }

        EmailTemplateRegistry::register('weekly_digest', [
            'label' => 'Weekly sales summary (owner)', 'required' => false,
            'variables' => ['name', 'restaurant', 'from', 'to', 'orders', 'revenue', 'average', 'revenue_change', 'top', 'reports_url'],
            'sample' => ['name' => 'Sam', 'restaurant' => 'Bella Italia', 'from' => 'Mar 2, 2026', 'to' => 'Mar 8, 2026', 'orders' => '184', 'revenue' => '$4,210.00', 'average' => '$22.88', 'revenue_change' => '+6.4%', 'top' => "- Margherita ×61\n- Cola ×48", 'reports_url' => 'https://example.com/reports'],
            'subject' => 'Last week at {{restaurant}}: {{orders}} orders, {{revenue}}',
            'body' => "Hi {{name}},\n\nHere is your week at **{{restaurant}}** ({{from}} to {{to}}):\n\n- Orders: **{{orders}}**\n- Revenue: **{{revenue}}** ({{revenue_change}} vs the week before)\n- Average order: **{{average}}**\n\nBest sellers:\n{{top}}\n\n[Open the reports]({{reports_url}})",
        ]);

        RestaurantNav::add('insights', 'panel.nav.reports', 'reports.index', 'reports.*', icon: 'activity', can: 'reports.view');
    }
}
