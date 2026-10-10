<?php

namespace App\Modules\Store\Providers;

use App\Modules\Admin\Support\AdminNav;
use App\Modules\Core\Support\RestaurantNav;
use App\Modules\Store\Console\ExpireStoreItems;
use App\Modules\Store\Services\Catalog;
use App\Modules\Store\Services\Entitlements;
use App\Modules\Store\Services\StoreAccess;
use Illuminate\Support\ServiceProvider;

/** Themes and premium features restaurants can buy or rent, priced and switched on or off by the platform. */
class StoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Catalog::class);
        $this->app->singleton(Entitlements::class);
        $this->app->singleton(StoreAccess::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'store');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->commands([ExpireStoreItems::class]);

        RestaurantNav::add('settings', 'panel.nav.store', 'store.index', 'store.*', icon: 'layers');
        AdminNav::add('billing', 'admin.nav.store', 'admin.store.index', 'admin.store.index', [], 'layers');
        AdminNav::add('billing', 'admin.nav.store_sales', 'admin.store.sales', 'admin.store.sales', [], 'receipt');
    }
}
