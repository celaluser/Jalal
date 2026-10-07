<?php

namespace App\Modules\Menu\Providers;

use App\Modules\Billing\Support\UsageRegistry;
use App\Modules\Core\Support\RestaurantNav;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use Illuminate\Support\ServiceProvider;

class MenuServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'menu');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        RestaurantNav::add('menu', 'panel.nav.menu', 'menu.index', 'menu.index|menu.categories.*|menu.products.*', icon: 'store', can: 'menu.manage');
        RestaurantNav::add('menu', 'panel.nav.options', 'menu.option-groups.index', 'menu.option-groups.*', icon: 'sliders', can: 'menu.manage');

        RestaurantNav::add('settings', 'panel.nav.appearance', 'appearance.edit', icon: 'layout', can: 'settings.manage');

        // Plan usage: these counters feed the bars and warnings on the subscription page.
        UsageRegistry::register('products', fn () => Product::count());
        UsageRegistry::register('categories', fn () => Category::count());
    }
}
