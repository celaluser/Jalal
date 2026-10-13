<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Services\ReportService;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tables\Models\DiningTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Landing screen of the restaurant panel. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, SubscriptionService $subscriptions, ReportService $reports): View|RedirectResponse
    {
        $user = $request->user();
        $restaurant = $user->restaurant;

        // First visit of an owner: walk through the setup wizard once.
        if ($restaurant && ! $restaurant->isOnboarded() && ! $request->session()->has('impersonator_id') && $user->can('settings.manage')) {
            return redirect()->route('onboarding.show');
        }

        $subscription = $restaurant ? $subscriptions->current($restaurant) : null;

        $stats = null;
        $steps = [];
        $recent = collect();

        if ($restaurant) {
            if ($user->can('reports.view')) {
                $today = $reports->build($restaurant, $reports->period($restaurant, 'today'), false);
                $yesterday = $reports->build($restaurant, $reports->period($restaurant, 'yesterday'), false);
                $week = $reports->build($restaurant, $reports->period($restaurant, '7'), false);
                $stats = [
                    'today' => $today, 'week' => $week,
                    'orders_delta' => $yesterday['orders'] ? round(($today['orders'] - $yesterday['orders']) / $yesterday['orders'] * 100) : null,
                    'revenue_delta' => $yesterday['revenue'] ? round(($today['revenue'] - $yesterday['revenue']) / $yesterday['revenue'] * 100) : null,
                    'open' => Order::whereIn('status', OrderStatus::OPEN)->count(),
                    'chart' => ['labels' => array_keys($week['daily']), 'values' => array_map(fn ($d) => round($d['revenue'] / 100, 2), array_values($week['daily']))],
                ];
            }

            if ($user->can('orders.view')) {
                $recent = Order::latest('id')->limit(5)->get(['id', 'number', 'status', 'type', 'total_cents', 'created_at']);
            }

            // First-run checklist: disappears once everything is done.
            $steps = [
                ['done' => $restaurant->logo_media_id !== null, 'route' => 'appearance.edit', 'key' => 'brand'],
                ['done' => Product::count() > 0, 'route' => 'menu.index', 'key' => 'dishes'],
                ['done' => DiningTable::count() > 0, 'route' => 'tables.index', 'key' => 'tables'],
                ['done' => Order::count() > 0, 'route' => 'tables.qr', 'key' => 'qr'],
            ];
        }

        return view('tenancy::dashboard', [
            'stats' => $stats, 'steps' => $steps, 'recent' => $recent,
            'user' => $user,
            'restaurant' => $restaurant,
            'subscription' => $subscription,
            'plan' => $subscription?->plan,
            'menuUrl' => $restaurant ? $restaurant->publicUrl() : null,
            'limits' => Plan::LIMITS,
            'features' => Plan::FEATURES,
        ]);
    }
}
