<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\DashboardService;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboard): View
    {
        $demo = $dashboard->demoEnabled() ? $dashboard->demo() : null;

        return view('admin::dashboard', [
            'isDemo' => $demo !== null,
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
