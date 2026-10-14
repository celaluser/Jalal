<?php

namespace App\Modules\Storefront\Providers;

use App\Modules\Core\Mail\EmailTemplateRegistry;
use App\Modules\Core\Support\RestaurantNav;
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
        EmailTemplateRegistry::register('account_login', [
            'label' => 'Guest sign-in link', 'required' => false, 'variables' => ['name', 'restaurant', 'login_url', 'minutes'],
            'sample' => ['name' => 'Sam', 'restaurant' => 'Bella Italia', 'login_url' => 'https://example.com/r/bella/account/verify/1', 'minutes' => '30'],
            'subject' => 'Your sign-in link · {{restaurant}}',
            'body' => "Hi {{name}},\n\nUse this link to see your orders at **{{restaurant}}**. It works for {{minutes}} minutes.\n\n[Sign in]({{login_url}})\n\nIf you did not ask for this, ignore this e-mail.",
        ]);

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        RestaurantNav::add('marketing', 'panel.nav.site', 'site.edit', 'site.*', icon: 'globe', can: 'marketing.manage');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'storefront');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        // Any change to the menu invalidates the cached customer menu of that restaurant.
        foreach ([Category::class, Product::class, OptionGroup::class, Option::class] as $model) {
            $model::saved(fn ($m) => MenuCache::bump($m->restaurant_id));
            $model::deleted(fn ($m) => MenuCache::bump($m->restaurant_id));
        }
    }
}
