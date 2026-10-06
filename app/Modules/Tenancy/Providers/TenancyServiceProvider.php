<?php

namespace App\Modules\Tenancy\Providers;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Http\Middleware\SetTenantFromUser;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $router->aliasMiddleware('tenant.resolve', ResolveTenant::class);
        $router->aliasMiddleware('tenant.user', SetTenantFromUser::class);

        // Jobs must never inherit the tenant of a previous job on the same worker.
        Event::listen(JobProcessing::class, fn () => app(TenantContext::class)->forget());
        Event::listen(JobProcessed::class, fn () => app(TenantContext::class)->forget());
    }
}
