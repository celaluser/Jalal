<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Tenancy\Services\RestaurantProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The first-run setup wizard: profile, branding, done. Every step can be skipped. */
class OnboardingController extends Controller
{
    public function __construct(private readonly RestaurantProfile $profile) {}

    public function show(Request $request, SubscriptionService $subscriptions): View|RedirectResponse
    {
        $restaurant = $request->user()->restaurant;

        if ($restaurant->isOnboarded()) {
            return redirect()->route('dashboard');
        }

        $step = min(3, max(1, (int) $request->query('step', 1)));

        return view('tenancy::onboarding.show', [
            'step' => $step,
            'restaurant' => $restaurant,
            'profile' => $this->profile,
            'menuUrl' => $restaurant->publicUrl(),
            'plan' => $subscriptions->current($restaurant)?->plan,
        ]);
    }

    public function profile(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        $this->profile->saveProfile($restaurant, $request->validate($this->profile->profileRules()));

        return redirect()->route('onboarding.show', ['step' => 2]);
    }

    public function branding(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        $this->profile->saveBranding($restaurant, $request->validate($this->profile->brandingRules()), $request->file('logo'));

        return redirect()->route('onboarding.show', ['step' => 3]);
    }

    public function finish(Request $request): RedirectResponse
    {
        $request->user()->restaurant->update(['onboarded_at' => now()]);

        return redirect()->route('dashboard')->with('status', __('onboarding.welcome_done'));
    }
}
