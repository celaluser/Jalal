<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Landing screen of the restaurant panel. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, SubscriptionService $subscriptions): View|RedirectResponse
    {
        $user = $request->user();
        $restaurant = $user->restaurant;

        // First visit of an owner: walk through the setup wizard once.
        if ($restaurant && ! $restaurant->isOnboarded() && ! $request->session()->has('impersonator_id') && $user->can('settings.manage')) {
            return redirect()->route('onboarding.show');
        }

        $subscription = $restaurant ? $subscriptions->current($restaurant) : null;

        return view('tenancy::dashboard', [
            'user' => $user,
            'restaurant' => $restaurant,
            'subscription' => $subscription,
            'plan' => $subscription?->plan,
            'menuUrl' => $restaurant ? url('/r/'.$restaurant->slug) : null,
            'limits' => Plan::LIMITS,
            'features' => Plan::FEATURES,
        ]);
    }
}
