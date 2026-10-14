<?php

namespace App\Modules\Branches\Providers;

use App\Modules\Billing\Support\UsageRegistry;
use App\Modules\Branches\Models\Branch;
use App\Modules\Branches\Services\BranchContext;
use App\Modules\Branches\Services\BranchMenu;
use App\Modules\Core\Support\RestaurantNav;
use Illuminate\Support\ServiceProvider;

/** Multi-branch restaurants: several locations under one account, each with its own tables, staff, hours and prices. */
class BranchesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BranchContext::class);
        $this->app->singleton(BranchMenu::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'branches');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        RestaurantNav::add('menu', 'panel.nav.branches', 'branches.index', 'branches.*', icon: 'store', can: 'branches.manage');

        UsageRegistry::register('branches', fn () => Branch::count());
    }
}
