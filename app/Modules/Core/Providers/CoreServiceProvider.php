<?php

namespace App\Modules\Core\Providers;

use App\Modules\Core\Services\DatabaseTranslationLoader;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: a fresh, empty context per request and per queue job.
        $this->app->scoped(TenantContext::class);
        $this->app->singleton(SettingsService::class);

        // extend() rather than singleton(): the translation provider is deferred and would override a plain binding.
        $this->app->extend('translation.loader', fn ($loader, $app) => new DatabaseTranslationLoader($app['files'], $app['path.lang']));
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
