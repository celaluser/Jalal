<?php

namespace App\Modules\Menu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Menu\Services\MenuService;
use App\Modules\Menu\Services\ThemeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
            'themes' => collect($this->themes->themes())->map(fn ($t, $key) => $t + ['locked' => ! $this->themes->allowed($restaurant, $key)])->all(),
            'canRemoveCredit' => $this->themes->canRemoveCredit($restaurant),
            // Real dishes if there are any, so the preview looks like their own menu.
            'sample' => collect($tree)->flatMap(fn ($c) => $c['products'])->take(3)->values()->all(),
            'accent' => $restaurant->brandColor(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        $data = $request->validate($this->themes->rules());

        if (! $this->themes->allowed($restaurant, $data['theme'])) {
            throw ValidationException::withMessages(['theme' => __('store.theme_locked_help')]);
        }

        $this->themes->save($restaurant, $data);

        return back()->with('status', __('admin.saved'));
    }
}
