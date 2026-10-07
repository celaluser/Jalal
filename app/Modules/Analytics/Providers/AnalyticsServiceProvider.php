<?php

namespace App\Modules\Analytics\Providers;

use App\Modules\Core\Support\RestaurantNav;
use Illuminate\Support\ServiceProvider;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'analytics');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        RestaurantNav::add('insights', 'panel.nav.reports', 'reports.index', 'reports.*', icon: 'activity', can: 'reports.view');
    }
}
