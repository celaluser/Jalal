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
        return view('admin::dashboard', [
            'totals' => $dashboard->totals(),
            'currency' => $dashboard->currency(),
            'signups' => $dashboard->signupsByDay(30),
            'revenue' => $dashboard->revenueByMonth(12),
            'latest' => $dashboard->latestRestaurants(),
            'tickets' => $dashboard->openTickets(),
            'restaurantNames' => Restaurant::withTrashed()->pluck('name', 'id'),
        ]);
    }
}
