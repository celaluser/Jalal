<?php

namespace Addons\HelloAddon;

use App\Modules\Core\Support\RestaurantNav;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** The entry point of the add-on. Everything the core offers (nav, events, mail templates, routes) is available here. */
class HelloServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // A public route. Use the 'web' middleware for sessions, CSRF and the locale.
        Route::middleware('web')->get('/hello-addon', fn () => 'Hello from an add-on');

        // A panel menu entry for restaurant staff (needs a route named like the first argument after the label).
        // RestaurantNav::add('settings', 'hello::nav', 'hello.index', icon: 'zap', can: 'settings.view');
    }
}
