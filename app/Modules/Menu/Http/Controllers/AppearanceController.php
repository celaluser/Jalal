<?php

namespace App\Modules\Menu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Menu\Services\MenuService;
use App\Modules\Menu\Services\ThemeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Theme, font, layout and corner style of the customer menu, with a live phone preview. */
class AppearanceController extends Controller
{
    public function __construct(private readonly ThemeRegistry $themes) {}

    public function edit(Request $request, MenuService $menu): View
    {
        $restaurant = $request->user()->restaurant;
        $tree = $menu->tree($restaurant);

        return view('menu::appearance.edit', [
            'restaurant' => $restaurant,
            'settings' => $this->themes->settings($restaurant),
            'themes' => $this->themes->themes(),
            'canRemoveCredit' => $this->themes->canRemoveCredit($restaurant),
            // Real dishes if there are any, so the preview looks like their own menu.
            'sample' => collect($tree)->flatMap(fn ($c) => $c['products'])->take(3)->values()->all(),
            'accent' => $restaurant->brandColor(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        $this->themes->save($restaurant, $request->validate($this->themes->rules()));

        return back()->with('status', __('admin.saved'));
    }
}
