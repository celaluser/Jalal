<?php

namespace App\Modules\Addons\Providers;

use App\Modules\Addons\Services\AddonManager;
use Illuminate\Support\ServiceProvider;

/**
 * Starts the enabled add-ons. Registered last in bootstrap/providers.php, so an add-on can use every registry of the core
 * (RestaurantNav, AdminNav, EmailTemplateRegistry, events, routes) from its own service provider.
 */
class AddonsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/addons.php'), 'addons');
        $this->app->singleton(AddonManager::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'addons-admin');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->app->make(AddonManager::class)->boot();
    }
}
