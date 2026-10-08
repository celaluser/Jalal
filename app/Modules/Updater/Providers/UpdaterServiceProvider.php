<?php

namespace App\Modules\Updater\Providers;

use App\Modules\Updater\Console\PackageBuild;
use App\Modules\Updater\Console\PackageKeys;
use Illuminate\Support\ServiceProvider;

class UpdaterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PackageKeys::class, PackageBuild::class]);
        }

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'updater');
    }
}
