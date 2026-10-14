<?php

namespace App\Modules\Tenancy\Providers;

use App\Modules\Core\Support\RestaurantNav;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Tenancy\Contracts\DnsResolver;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Http\Middleware\SetTenantFromUser;
use App\Modules\Tenancy\Support\NativeDnsResolver;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DnsResolver::class, NativeDnsResolver::class);
    }

    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'tenancy');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        RestaurantNav::add('main', 'panel.nav.dashboard', 'dashboard', icon: 'grid');
        RestaurantNav::add('settings', 'panel.nav.restaurant', 'restaurant.settings', icon: 'store', can: 'settings.manage');
        RestaurantNav::add('settings', 'panel.nav.domains', 'domains.index', icon: 'globe', can: 'settings.manage');

        $router->aliasMiddleware('tenant.resolve', ResolveTenant::class);
        $router->aliasMiddleware('tenant.user', SetTenantFromUser::class);

        // Jobs must never inherit the tenant of a previous job on the same worker.
        Event::listen(JobProcessing::class, fn () => app(TenantContext::class)->forget());
        Event::listen(JobProcessed::class, fn () => app(TenantContext::class)->forget());
    }
}
