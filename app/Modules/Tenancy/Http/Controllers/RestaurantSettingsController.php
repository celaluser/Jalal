<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Services\RestaurantProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Restaurant profile and branding, editable at any time after the wizard. */
class RestaurantSettingsController extends Controller
{
    public function __construct(private readonly RestaurantProfile $profile) {}

    public function edit(Request $request): View
    {
        return view('tenancy::settings.restaurant', ['restaurant' => $request->user()->restaurant, 'profile' => $this->profile]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $this->profile->saveProfile($request->user()->restaurant, $request->validate($this->profile->profileRules()));

        return back()->with('status', __('admin.saved'));
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        $this->profile->saveBranding($request->user()->restaurant, $request->validate($this->profile->brandingRules()), $request->file('logo'));

        return back()->with('status', __('admin.saved'));
    }
}
