<?php

namespace App\Modules\Core\Providers;

use App\Modules\Core\Services\DatabaseTranslationLoader;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: a fresh, empty context per request and per queue job.
        $this->app->scoped(TenantContext::class);
        $this->app->singleton(SettingsService::class);

        $this->app->singleton('translation.loader', fn ($app) => new DatabaseTranslationLoader($app['files'], $app['path.lang']));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
