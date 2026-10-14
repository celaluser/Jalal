<?php

namespace App\Modules\Menu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Services\AiPanel;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Services\MenuImage;
use App\Modules\Menu\Services\MenuService;
use App\Modules\Menu\Support\Schedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private readonly MenuService $menu, private readonly MenuImage $images) {}

    public function create(Request $request): View
    {
        return view('menu::categories.form', ['category' => new Category(['is_active' => true]), 'locales' => $request->user()->restaurant->menuLocales(), 'ai' => app(AiPanel::class)->for($request->user()->restaurant)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;

        if (! $this->menu->canAdd($restaurant, 'categories')) {
            return back()->withInput()->withErrors(['limit' => __('menu.limit_categories')]);
        }

        $data = $this->validated($request);
        $category = Category::create($data['fields'] + ['sort' => $this->menu->nextSort(Category::class), 'image_media_id' => $this->images->sync(null, $request->file('image'), false) ?: null]);

        return redirect()->route('menu.index', ['category' => $category->id])->with('status', __('menu.category_created'));
    }

    public function edit(Request $request, Category $category): View
    {
        return view('menu::categories.form', ['category' => $category, 'locales' => $request->user()->restaurant->menuLocales(), 'ai' => app(AiPanel::class)->for($request->user()->restaurant)]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $attributes = $this->validated($request)['fields'];
        $image = $this->images->sync($category->image_media_id, $request->file('image'), $request->boolean('remove_image'));

        if ($image !== false) {
            $attributes['image_media_id'] = $image;
        }

        $category->update($attributes);

        return redirect()->route('menu.index', ['category' => $category->id])->with('status', __('admin.saved'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->withErrors(['category' => __('menu.category_not_empty')]);
        }

        $category->delete();

        return redirect()->route('menu.index')->with('status', __('menu.category_deleted'));
    }

    /** @return array{fields: array<string, mixed>} */
    private function validated(Request $request): array
    {
        $locales = $request->user()->restaurant->menuLocales();
        $request->validate([
            'name' => ['required', 'array'],
            "name.{$locales[0]}" => ['required', 'string', 'max:120'],
            'name.*' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'max:4096'],
            'icon' => ['nullable', 'string', 'max:16'],
            'schedule' => ['nullable', 'array'],
            'locked_locales' => ['nullable', 'array'], 'locked_locales.*' => ['string', 'in:'.implode(',', $locales)],
        ]);

        return ['fields' => [
            'name' => Category::cleanTranslations($request->input('name', []), $locales),
            'description' => Category::cleanTranslations($request->input('description', []), $locales) ?: null,
            'icon' => $request->filled('icon') ? mb_substr(trim($request->input('icon')), 0, 16) : null,
            'schedule' => Schedule::fromInput($request->input('schedule')),
            'locked_locales' => array_values(array_intersect($locales, (array) $request->input('locked_locales', []))) ?: null,
            'is_active' => $request->boolean('is_active'),
        ]];
    }
}
