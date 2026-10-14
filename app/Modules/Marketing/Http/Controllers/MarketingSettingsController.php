<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Services\MarketingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Loyalty reward, review and e-mail options. */
class MarketingSettingsController extends Controller
{
    public function __construct(private readonly MarketingSettings $settings) {}

    public function edit(Request $request): View
    {
        $restaurant = $request->user()->restaurant;

        return view('marketing::loyalty.edit', ['restaurant' => $restaurant, 's' => $this->settings->for($restaurant), 'ceiling' => (int) config('marketing.daily_email_cap')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->settings->save($request->user()->restaurant, $request->validate($this->settings->rules()));

        return back()->with('status', __('admin.saved'));
    }
}
