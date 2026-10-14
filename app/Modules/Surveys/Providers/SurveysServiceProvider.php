<?php

namespace App\Modules\Surveys\Providers;

use App\Modules\Core\Support\RestaurantNav;
use Illuminate\Support\ServiceProvider;

/** Surveys: short questionnaires (rating, NPS, choice, text) shared by link, QR code or after an order. */
class SurveysServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'surveys');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        RestaurantNav::add('marketing', 'surveys.nav', 'surveys.index', 'surveys.*', icon: 'star', can: 'marketing.manage');
    }
}
