<?php

namespace App\Modules\Orders\Providers;

use App\Modules\Core\Support\RestaurantNav;
use Illuminate\Support\ServiceProvider;

class OrdersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'orders');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        RestaurantNav::add('orders', 'panel.nav.orders', 'orders.board', 'orders.board|orders.show', icon: 'bell', can: 'orders.view');
        RestaurantNav::add('settings', 'panel.nav.ordering', 'orders.settings', icon: 'sliders', can: 'settings.manage');
    }
}
