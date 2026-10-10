<?php

namespace App\Modules\Support\Providers;

use App\Modules\Core\Support\RestaurantNav;
use Illuminate\Support\ServiceProvider;

class SupportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'support');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        RestaurantNav::add('help', 'panel.nav.support', 'support.index', 'support.*', icon: 'life-buoy', can: 'support.manage');
    }
}
