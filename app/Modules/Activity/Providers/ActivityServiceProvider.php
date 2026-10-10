<?php

namespace App\Modules\Activity\Providers;

use App\Modules\Core\Support\RestaurantNav;
use Illuminate\Support\ServiceProvider;

class ActivityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'activity');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        RestaurantNav::add('settings', 'panel.nav.activity', 'activity.index', icon: 'file-text', can: 'activity.view');
    }
}
