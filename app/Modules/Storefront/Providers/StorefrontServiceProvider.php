<?php

namespace App\Modules\Storefront\Providers;

use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Option;
use App\Modules\Menu\Models\OptionGroup;
use App\Modules\Menu\Models\Product;
use App\Modules\Storefront\Services\MenuCache;
use Illuminate\Support\ServiceProvider;

class StorefrontServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'storefront');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        // Any change to the menu invalidates the cached customer menu of that restaurant.
        foreach ([Category::class, Product::class, OptionGroup::class, Option::class] as $model) {
            $model::saved(fn ($m) => MenuCache::bump($m->restaurant_id));
            $model::deleted(fn ($m) => MenuCache::bump($m->restaurant_id));
        }
    }
}
