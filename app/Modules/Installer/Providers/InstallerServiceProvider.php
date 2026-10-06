<?php

namespace App\Modules\Installer\Providers;

use App\Modules\Installer\Http\Middleware\EnsureInstalled;
use App\Modules\Installer\Services\EnvWriter;
use App\Modules\Installer\Services\InstallerService;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;
use Throwable;

class InstallerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InstallerService::class);
        $this->prepareFreshInstall();
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'installer');

        // Global and first: group middleware is re-sorted by priority and `auth` would run before it.
        $this->app->make(HttpKernel::class)->prependMiddleware(EnsureInstalled::class);
    }

    /**
     * Before installation there is no database: use file based session/cache, and make sure an
     * APP_KEY exists (needed to encrypt the session cookie).
     */
    private function prepareFreshInstall(): void
    {
        if (config('installer.force_installed') || is_file(config('installer.lock_file'))) {
            return;
        }

        config([
            'session.driver' => 'file',
            'cache.default' => 'file',
            'queue.default' => 'sync',
        ]);

        if (config('app.key')) {
            return;
        }

        try {
            $key = 'base64:'.base64_encode(random_bytes(32));
            (new EnvWriter)->set(['APP_KEY' => $key]);
            config(['app.key' => $key]);
        } catch (Throwable) {
            // The requirements step reports a non-writable .env; nothing else to do here.
            config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        }
    }
}
