<?php

namespace App\Modules\Menu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\OptionGroup;
use App\Modules\Menu\Models\Product;
use App\Modules\Menu\Services\MenuImage;
use App\Modules\Menu\Services\MenuService;
use App\Modules\Storefront\Services\MenuCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private readonly MenuService $menu, private readonly MenuImage $images) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (! Category::exists()) {
            return redirect()->route('menu.categories.create')->withErrors(['category' => __('menu.need_category')]);
        }

        return view('menu::products.form', $this->formData($request, new Product(['is_active' => true, 'is_available' => true, 'category_id' => $request->query('category')])));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->menu->canAdd($request->user()->restaurant, 'products')) {
            return back()->withInput()->withErrors(['limit' => __('menu.limit_products')]);
        }

        $fields = $this->validated($request);
        $product = Product::create($fields + [
            'sort' => $this->menu->nextSort(Product::class, ['category_id' => $fields['category_id']]),
            'image_media_id' => $this->images->sync(null, $request->file('image'), false) ?: null,
        ]);
        $this->syncGroups($product, $request);

        return redirect()->route('menu.index', ['category' => $product->category_id])->with('status', __('menu.product_created'));
    }

    public function edit(Request $request, Product $product): View
    {
        return view('menu::products.form', $this->formData($request, $product->load('optionGroups')));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $fields = $this->validated($request);
        $image = $this->images->sync($product->image_media_id, $request->file('image'), $request->boolean('remove_image'));

        if ($image !== false) {
            $fields['image_media_id'] = $image;
        }

        if ((int) $fields['category_id'] !== $product->category_id) {
            $fields['sort'] = $this->menu->nextSort(Product::class, ['category_id' => $fields['category_id']]);
        }

        $product->update($fields);
        $this->syncGroups($product, $request);

        return redirect()->route('menu.index', ['category' => $product->category_id])->with('status', __('admin.saved'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $category = $product->category_id;
        $product->optionGroups()->detach();
        $product->delete();

        return redirect()->route('menu.index', ['category' => $category])->with('status', __('menu.product_deleted'));
    }

    /** Flip one of the quick switches (visible / sold out / featured) from the list. */
    public function toggle(Request $request, Product $product): RedirectResponse
    {
        $field = $request->validate(['field' => ['required', Rule::in(Product::TOGGLES)]])['field'];
        $product->update([$field => ! $product->{$field}]);

        return back();
    }

    public function duplicate(Request $request, Product $product): RedirectResponse
    {
        if (! $this->menu->canAdd($request->user()->restaurant, 'products')) {
            return back()->withErrors(['limit' => __('menu.limit_products')]);
        }

        $copy = $this->menu->duplicate($product);

        return redirect()->route('menu.products.edit', $copy)->with('status', __('menu.product_duplicated'));
    }

    /** @return array<string, mixed> */
    private function formData(Request $request, Product $product): array
    {
        return [
            'product' => $product,
            'restaurant' => $request->user()->restaurant,
            'locales' => $request->user()->restaurant->menuLocales(),
            'categories' => Category::orderBy('sort')->get(),
            'groups' => OptionGroup::with('options')->orderBy('sort')->get(),
            'selectedGroups' => $product->exists ? $product->optionGroups->pluck('id')->all() : [],
            'allergens' => config('menu.allergens'),
            'dietary' => config('menu.dietary'),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $locales = $request->user()->restaurant->menuLocales();
        $tenant = app(TenantContext::class)->id();

        $request->validate([
            'category_id' => ['required', Rule::exists('categories', 'id')->where('restaurant_id', $tenant)],
            'name' => ['required', 'array'],
            "name.{$locales[0]}" => ['required', 'string', 'max:160'],
            'name.*' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'compare_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'calories' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'prep_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'allergens' => ['nullable', 'array'],
            'allergens.*' => [Rule::in(config('menu.allergens'))],
            'dietary' => ['nullable', 'array'],
            'dietary.*' => [Rule::in(config('menu.dietary'))],
            'option_groups' => ['nullable', 'array'],
            'option_groups.*' => [Rule::exists('option_groups', 'id')->where('restaurant_id', $tenant)],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        return [
            'category_id' => (int) $request->input('category_id'),
            'name' => Product::cleanTranslations($request->input('name', []), $locales),
            'description' => Product::cleanTranslations($request->input('description', []), $locales) ?: null,
            'price' => $request->input('price'),
            'compare_price' => $request->filled('compare_price') ? $request->input('compare_price') : null,
            'calories' => $request->filled('calories') ? (int) $request->input('calories') : null,
            'prep_minutes' => $request->filled('prep_minutes') ? (int) $request->input('prep_minutes') : null,
            'allergens' => array_values($request->input('allergens', [])) ?: null,
            'dietary' => array_values($request->input('dietary', [])) ?: null,
            'is_active' => $request->boolean('is_active'),
            'is_available' => $request->boolean('is_available'),
            'is_featured' => $request->boolean('is_featured'),
        ];
    }

    private function syncGroups(Product $product, Request $request): void
    {
        $ids = array_values(array_unique(array_map('intval', $request->input('option_groups', []))));
        $product->optionGroups()->sync(collect($ids)->mapWithKeys(fn ($id, $i) => [$id => ['sort' => $i]])->all());
        MenuCache::bump($product->restaurant_id); // pivot changes fire no model events
    }
}
