<?php

namespace App\Modules\Ai\Providers;

use App\Modules\Ai\Services\AiCredits;
use App\Modules\Billing\Support\UsageRegistry;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'ai');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        // AI credits used this month show up beside the other plan limits.
        UsageRegistry::register('ai_credits', fn () => app(AiCredits::class)->used());
    }
}
