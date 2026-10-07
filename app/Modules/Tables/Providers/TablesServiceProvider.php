<?php

namespace App\Modules\Tables\Providers;

use App\Modules\Billing\Support\UsageRegistry;
use App\Modules\Core\Support\RestaurantNav;
use App\Modules\Tables\Models\DiningTable;
use Illuminate\Support\ServiceProvider;

class TablesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'tables');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        RestaurantNav::add('menu', 'panel.nav.tables', 'tables.index', 'tables.*', icon: 'qr', can: 'tables.view');

        UsageRegistry::register('tables', fn () => DiningTable::count());
    }
}
