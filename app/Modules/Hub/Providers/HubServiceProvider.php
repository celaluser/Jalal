<?php

namespace App\Modules\Hub\Providers;

use App\Modules\Core\Support\RestaurantNav;
use App\Modules\Hub\Support\ChannelRegistry;
use App\Modules\Hub\Support\GenericAdapter;
use Illuminate\Support\ServiceProvider;

/** Order hub: orders from delivery platforms, phone bridges, Zapier or Make arrive on the same kitchen board as QR orders. */
class HubServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'hub');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        ChannelRegistry::register(new GenericAdapter);

        RestaurantNav::add('orders', 'panel.nav.channels', 'channels.index', 'channels.*', icon: 'zap', can: 'api.manage');
    }
}
