<?php

namespace App\Modules\Core\Providers;

use App\Modules\Core\Http\Middleware\MaintenanceMode;
use App\Modules\Core\Services\DatabaseTranslationLoader;
use App\Modules\Core\Services\RuntimeSettings;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
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
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'core');

        // Through the kernel: it re-syncs its groups to the router, which would drop a router-level push.
        $this->app->make(HttpKernel::class)->appendMiddlewareToGroup('web', MaintenanceMode::class);

        $this->applyRuntimeSettings();
    }

    /**
     * Admin-managed settings override .env defaults. Skipped before installation (no database yet);
     * a broken database must never take the whole site down, so failures are swallowed.
     */
    private function applyRuntimeSettings(): void
    {
        if (! (config('installer.force_installed') || is_file(config('installer.lock_file')))) {
            return;
        }

        try {
            $this->app->make(RuntimeSettings::class)->apply();
        } catch (\Throwable) {
            // keep .env values
        }
    }
}
