<?php

namespace App\Modules\Api\Providers;

use App\Modules\Api\Http\Middleware\AuthenticateApiToken;
use App\Modules\Api\Services\WebhookDispatcher;
use App\Modules\Core\Support\RestaurantNav;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'api');
        Route::aliasMiddleware('api.token', AuthenticateApiToken::class);
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        RateLimiter::for('api-token', fn (Request $r) => Limit::perMinute(120)->by('api-token:'.($r->attributes->get('api_token')?->id ?? $r->ip())));

        RestaurantNav::add('settings', 'panel.nav.integrations', 'integrations.index', 'integrations.*', icon: 'zap', can: 'api.manage');

        Event::subscribe(new class
        {
            public function subscribe($events): void
            {
                app(WebhookDispatcher::class)->subscribe($events);
            }
        });
    }
}
