<?php

namespace App\Modules\Inventory\Providers;

use App\Modules\Core\Support\RestaurantNav;
use App\Modules\Inventory\Services\Recipes;
use Illuminate\Support\ServiceProvider;

/** Raw-material stock, deliveries, suppliers and recipes: ordering a dish uses up its ingredients and sets what it costs to make. */
class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Recipes::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'inventory');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        RestaurantNav::add('menu', 'panel.nav.inventory', 'inventory.index', 'inventory.*', icon: 'clipboard', can: 'menu.manage');
    }
}
