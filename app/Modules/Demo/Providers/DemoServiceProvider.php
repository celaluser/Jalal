<?php

namespace App\Modules\Demo\Providers;

use App\Modules\Demo\Http\Middleware\BlockDemoActions;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;

class DemoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(HttpKernel::class)->appendMiddlewareToGroup('web', BlockDemoActions::class);
    }
}
