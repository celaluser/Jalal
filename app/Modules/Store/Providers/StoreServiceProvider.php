<?php

namespace App\Modules\Store\Providers;

use App\Modules\Admin\Support\AdminNav;
use App\Modules\Core\Mail\EmailTemplateRegistry;
use App\Modules\Core\Support\RestaurantNav;
use App\Modules\Store\Console\ExpireStoreItems;
use App\Modules\Store\Console\RemindStoreItems;
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
        $this->commands([ExpireStoreItems::class, RemindStoreItems::class]);

        EmailTemplateRegistry::register('store_expiring', [
            'label' => 'Store item ending soon (owner)', 'required' => false, 'variables' => ['name', 'restaurant', 'item', 'ends_at', 'days_left', 'store_url', 'app_name'],
            'sample' => ['name' => 'Ada', 'restaurant' => 'Bella Italia', 'item' => 'Aurora theme', 'ends_at' => '2026-11-01', 'days_left' => '3', 'store_url' => 'https://example.com/store/theme:aurora', 'app_name' => 'QR Menu'],
            'subject' => '{{item}} ends in {{days_left}} days',
            'body' => "Hi {{name}},\n\n**{{item}}** for **{{restaurant}}** ends on **{{ends_at}}**. Extend it now so nothing changes on your menu.\n\n[Extend {{item}}]({{store_url}})",
        ]);
        EmailTemplateRegistry::register('ingredient_low', [
            'label' => 'Ingredient running low (owner)', 'required' => false, 'variables' => ['restaurant', 'items', 'inventory_url'],
            'sample' => ['restaurant' => 'Bella Italia', 'items' => 'Flour (2 kg left), Mozzarella (0 kg left)', 'inventory_url' => 'https://example.com/inventory'],
            'subject' => 'Ingredients running low · {{restaurant}}',
            'body' => "Some ingredients are almost used up at **{{restaurant}}**:\n\n{{items}}\n\n[Open the stock list]({{inventory_url}})",
        ]);

        RestaurantNav::add('settings', 'panel.nav.store', 'store.index', 'store.*', icon: 'layers');
        AdminNav::add('billing', 'admin.nav.store', 'admin.store.index', 'admin.store.index', [], 'layers');
        AdminNav::add('billing', 'admin.nav.store_sales', 'admin.store.sales', 'admin.store.sales', [], 'receipt');
    }
}
