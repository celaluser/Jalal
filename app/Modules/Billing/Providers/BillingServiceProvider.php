<?php

namespace App\Modules\Billing\Providers;

use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'billing');
    }
}
