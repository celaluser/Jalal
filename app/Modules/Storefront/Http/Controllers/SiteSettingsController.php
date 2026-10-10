<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Storefront\Services\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Panel page for the mini website and search-engine options. */
class SiteSettingsController extends Controller
{
    public function __construct(private readonly SiteSettings $site) {}

    public function edit(Request $request): View
    {
        $restaurant = $request->user()->restaurant;

        return view('storefront::admin.site', ['restaurant' => $restaurant, 's' => $this->site->for($restaurant), 'locales' => $restaurant->menuLocales()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        $this->site->save($restaurant, $request->validate($this->site->rules()));

        return back()->with('status', __('admin.saved'));
    }
}
