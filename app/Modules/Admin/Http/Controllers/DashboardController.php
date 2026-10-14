<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\DashboardService;
use App\Modules\Admin\Services\SystemInfo;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Payments\GatewayManager;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboard, GatewayManager $gateways, SettingsService $settings, SystemInfo $system): View
    {
        $steps = [
            ['done' => (bool) $settings->get('general.logo') || ($settings->get('site.name') && $settings->get('site.name') !== config('app.name')), 'route' => route('admin.settings.section', 'general'), 'key' => 'brand'],
            ['done' => Plan::count() > 0, 'route' => route('admin.plans.index'), 'key' => 'plans'],
            ['done' => collect($gateways->all())->keys()->contains(fn ($code) => $gateways->isReady($code)), 'route' => route('admin.settings.payments'), 'key' => 'payments'],
            ['done' => (bool) $settings->get('mail.host'), 'route' => route('admin.settings.section', 'mail'), 'key' => 'mail'],
            ['done' => $system->workers()['scheduler']['state'] === 'ok', 'route' => route('admin.system.index'), 'key' => 'cron'],
        ];

        $demo = $dashboard->demoEnabled() ? $dashboard->demo() : null;

        return view('admin::dashboard', [
            'isDemo' => $demo !== null,
            'steps' => $steps,
            'totals' => $demo['totals'] ?? $dashboard->totals(),
            'currency' => $dashboard->currency(),
            'signups' => $demo['signups'] ?? $dashboard->signupsByDay(30),
            'revenue' => $demo['revenue'] ?? $dashboard->revenueByMonth(12),
            'latest' => $dashboard->latestRestaurants(),
            'tickets' => $dashboard->openTickets(),
            'restaurantNames' => Restaurant::withTrashed()->pluck('name', 'id'),
        ]);
    }
}
