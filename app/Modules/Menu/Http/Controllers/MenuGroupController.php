<?php

namespace App\Modules\Menu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Menu;
use App\Modules\Menu\Services\MenuService;
use App\Modules\Menu\Support\Schedule;
use App\Modules\Storefront\Services\MenuCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Menus (breakfast, lunch, drinks...) and which categories belong to each. */
class MenuGroupController extends Controller
{
    public function __construct(private readonly MenuService $menu) {}

    public function index(Request $request): View
    {
        return view('menu::menus.index', ['menus' => Menu::withCount('categories')->orderBy('sort')->orderBy('id')->get(), 'restaurant' => $request->user()->restaurant]);
    }

    public function create(Request $request): View
    {
        return view('menu::menus.form', ['menu' => new Menu(['is_active' => true]), 'locales' => $request->user()->restaurant->menuLocales(), 'categories' => Category::orderBy('sort')->get(), 'restaurant' => $request->user()->restaurant]);
    }

    public function store(Request $request): RedirectResponse
    {
        $menu = Menu::create($this->fields($request) + ['sort' => $this->menu->nextSort(Menu::class)]);
        $this->assign($menu, $request);

        return redirect()->route('menu.menus.index')->with('status', __('menu.menu_saved'));
    }

    public function edit(Request $request, Menu $menu): View
    {
        return view('menu::menus.form', ['menu' => $menu, 'locales' => $request->user()->restaurant->menuLocales(), 'categories' => Category::orderBy('sort')->get(), 'restaurant' => $request->user()->restaurant]);
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $menu->update($this->fields($request));
        $this->assign($menu, $request);

        return redirect()->route('menu.menus.index')->with('status', __('menu.menu_saved'));
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        // Its categories stay, and go back to the main menu.
        Category::where('menu_id', $menu->id)->update(['menu_id' => null]);
        $menu->delete();
        MenuCache::bump($menu->restaurant_id);

        return redirect()->route('menu.menus.index')->with('status', __('menu.menu_deleted'));
    }

    /** @return array<string, mixed> */
    private function fields(Request $request): array
    {
        $locales = $request->user()->restaurant->menuLocales();
        $request->validate([
            'name' => ['required', 'array'], "name.{$locales[0]}" => ['required', 'string', 'max:80'], 'name.*' => ['nullable', 'string', 'max:80'],
            'schedule' => ['nullable', 'array'], 'categories' => ['nullable', 'array'],
        ]);

        return ['name' => Menu::cleanTranslations($request->input('name', []), $locales), 'schedule' => Schedule::fromInput($request->input('schedule')), 'is_active' => $request->boolean('is_active')];
    }

    /** Puts the ticked categories into this menu and takes the others out of it (tenant scope keeps foreign ids out). */
    private function assign(Menu $menu, Request $request): void
    {
        $ids = array_map('intval', (array) $request->input('categories', []));
        Category::where('menu_id', $menu->id)->whereNotIn('id', $ids)->update(['menu_id' => null]);
        Category::whereIn('id', $ids)->update(['menu_id' => $menu->id]);
        MenuCache::bump($menu->restaurant_id);
    }
}
