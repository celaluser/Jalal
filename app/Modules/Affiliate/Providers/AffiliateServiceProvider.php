<?php

namespace App\Modules\Affiliate\Providers;

use App\Modules\Affiliate\Services\AffiliateService;
use App\Modules\Core\Support\RestaurantNav;
use Illuminate\Support\ServiceProvider;

class AffiliateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AffiliateService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'affiliate');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        RestaurantNav::add('settings', 'panel.nav.referrals', 'referrals.index', icon: 'gift', can: 'billing.manage', when: fn () => app(AffiliateService::class)->enabled());
    }
}
