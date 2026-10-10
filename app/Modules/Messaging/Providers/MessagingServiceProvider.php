<?php

namespace App\Modules\Messaging\Providers;

use App\Modules\Admin\Support\AdminNav;
use App\Modules\Messaging\Services\MessagingManager;
use Illuminate\Support\ServiceProvider;

class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MessagingManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'messaging');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        AdminNav::add('system', 'admin.nav.messaging', 'admin.messaging.edit', 'admin.messaging.*', [], 'send');
    }
}
