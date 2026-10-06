<?php

namespace App\Modules\Auth\Providers;

use App\Modules\Auth\Http\Middleware\VerifyRecaptcha;
use App\Modules\Auth\Listeners\SendWelcomeEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $router->aliasMiddleware('recaptcha', VerifyRecaptcha::class);
        Event::listen(Registered::class, SendWelcomeEmail::class);
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'auth-module');
    }
}
