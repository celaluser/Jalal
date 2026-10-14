<?php

namespace App\Modules\Chat\Providers;

use App\Modules\Core\Support\RestaurantNav;
use Illuminate\Support\ServiceProvider;

/** Live chat between a guest and the staff, tied to the guest's order. Polling, so it needs nothing but PHP. */
class ChatServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'chat');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        RestaurantNav::add('orders', 'chat.nav', 'chat.index', 'chat.*', icon: 'bell', can: 'orders.view');
    }
}
