<?php

namespace App\Modules\Menu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Services\AiPanel;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\OptionGroup;
use App\Modules\Menu\Models\Product;
use App\Modules\Menu\Services\MenuImage;
use App\Modules\Menu\Services\MenuService;
use App\Modules\Menu\Services\ProductMedia;
use App\Modules\Menu\Support\Schedule;
use App\Modules\Storefront\Services\MenuCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private readonly MenuService $menu, private readonly MenuImage $images, private readonly ProductMedia $media) {}

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
            'gallery' => $this->media->syncGallery(null, $request->file('gallery', []), []) ?: null,
        ]);
        $this->syncGroups($product, $request);
        $this->syncVariants($product, $request);
        $this->syncPairings($product, $request);
        $this->syncCombo($product, $request);

        return redirect()->route('menu.index', ['category' => $product->category_id])->with('status', __('menu.product_created'));
    }

    public function edit(Request $request, Product $product): View
    {
        return view('menu::products.form', $this->formData($request, $product->load(['optionGroups', 'variants', 'pairings', 'comboSlots.items'])));
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

        $fields['gallery'] = $this->media->syncGallery($product, $request->file('gallery', []), (array) $request->input('remove_gallery', [])) ?: null;

        $product->update($fields);
        $this->syncGroups($product, $request);
        $this->syncVariants($product, $request);
        $this->syncPairings($product, $request);
        $this->syncCombo($product, $request);

        return redirect()->route('menu.index', ['category' => $product->category_id])->with('status', __('admin.saved'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $category = $product->category_id;
        $product->optionGroups()->detach();
        $this->media->purge($product);
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
            'comboSlots' => $product->exists ? $product->comboSlots->map(fn ($s) => ['name' => $s->name, 'items' => $s->items->map(fn ($i) => ['product_id' => $i->product_id, 'price_delta' => (string) $i->price_delta])->all()])->all() : [],
            'otherProducts' => Product::whereKeyNot($product->id ?? 0)->orderBy('category_id')->orderBy('sort')->get(['id', 'name']),
            'pairedIds' => $product->exists ? $product->pairings->pluck('id')->all() : [],
            'allergens' => config('menu.allergens'),
            'dietary' => config('menu.dietary'),
            'ai' => app(AiPanel::class)->for($request->user()->restaurant),
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
            'stock_qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'low_stock_at' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'allergens' => ['nullable', 'array'],
            'allergens.*' => [Rule::in(config('menu.allergens'))],
            'dietary' => ['nullable', 'array'],
            'dietary.*' => [Rule::in(config('menu.dietary'))],
            'option_groups' => ['nullable', 'array'],
            'option_groups.*' => [Rule::exists('option_groups', 'id')->where('restaurant_id', $tenant)],
            'image' => ['nullable', 'image', 'max:4096'],
            'gallery' => ['nullable', 'array', 'max:'.ProductMedia::MAX_GALLERY],
            'gallery.*' => ['image', 'max:4096'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'video_url' => ['nullable', 'url:https', 'max:500', function ($attr, $value, $fail) {
                if ($value && ProductMedia::video($value) === null) {
                    $fail(__('menu.video_unsupported'));
                }
            }],
            'portion_size' => ['nullable', 'string', 'max:60'],
            'spice_level' => ['nullable', 'integer', 'between:0,3'],
            'nutrition' => ['nullable', 'array'],
            'nutrition.*' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'badges' => ['nullable', 'array'],
            'badges.*' => [Rule::in(Product::BADGES)],
            'limited_until' => ['nullable', 'date'],
            'schedule' => ['nullable', 'array'],
            'variants' => ['nullable', 'array', 'max:12'],
            'variants.*.name' => ['nullable', 'array'],
            'variants.*.name.*' => ['nullable', 'string', 'max:80'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'variants.*.id' => ['nullable', 'integer'],
            'combo' => ['nullable', 'array'],
            'combo.slots' => ['nullable', 'array', 'max:8'],
            'combo.slots.*.name' => ['nullable', 'array'],
            'combo.slots.*.name.*' => ['nullable', 'string', 'max:80'],
            'combo.slots.*.items' => ['nullable', 'array', 'max:20'],
            'combo.slots.*.items.*.product_id' => [Rule::exists('products', 'id')->where('restaurant_id', $tenant)],
            'combo.slots.*.items.*.price_delta' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'pairings' => ['nullable', 'array', 'max:12'],
            'pairings.*' => [Rule::exists('products', 'id')->where('restaurant_id', $tenant)],
            'order_types' => ['nullable', 'array'],
            'order_types.*' => [Rule::in(['dine_in', 'takeaway', 'delivery'])],
        ]);

        return [
            'category_id' => (int) $request->input('category_id'),
            'name' => Product::cleanTranslations($request->input('name', []), $locales),
            'description' => Product::cleanTranslations($request->input('description', []), $locales) ?: null,
            'price' => $request->input('price'),
            'compare_price' => $request->filled('compare_price') ? $request->input('compare_price') : null,
            'calories' => $request->filled('calories') ? (int) $request->input('calories') : null,
            'prep_minutes' => $request->filled('prep_minutes') ? (int) $request->input('prep_minutes') : null,
            'stock_qty' => $request->filled('stock_qty') ? (int) $request->input('stock_qty') : null,
            'low_stock_at' => $request->filled('stock_qty') && $request->filled('low_stock_at') ? (int) $request->input('low_stock_at') : null,
            'cost_price' => $request->filled('cost_price') ? $request->input('cost_price') : null,
            'video_url' => $request->filled('video_url') ? trim($request->input('video_url')) : null,
            'portion_size' => $request->filled('portion_size') ? trim($request->input('portion_size')) : null,
            'spice_level' => (int) $request->input('spice_level', 0),
            'nutrition' => collect($request->input('nutrition', []))->only(Product::NUTRIENTS)->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v) => (float) $v)->all() ?: null,
            'badges' => array_values(array_intersect(Product::BADGES, (array) $request->input('badges', []))) ?: null,
            'limited_until' => $request->filled('limited_until') ? $request->input('limited_until') : null,
            'schedule' => Schedule::fromInput($request->input('schedule')),
            // Ticking all three order types is the same as no restriction.
            'order_types' => (count($types = array_values(array_intersect(['dine_in', 'takeaway', 'delivery'], (array) $request->input('order_types', [])))) > 0 && count($types) < 3) ? $types : null,
            'allergens' => array_values($request->input('allergens', [])) ?: null,
            'dietary' => array_values($request->input('dietary', [])) ?: null,
            'is_active' => $request->boolean('is_active'),
            'is_available' => $request->boolean('is_available'),
            'is_featured' => $request->boolean('is_featured'),
            'is_combo' => $request->boolean('is_combo'),
        ];
    }

    /**
     * Saves the size/portion rows. Rows keep their id when edited (so a guest's open cart still points at them),
     * rows left out are removed, and a row with no name or no price is ignored.
     */
    private function syncVariants(Product $product, Request $request): void
    {
        $locales = $request->user()->restaurant->menuLocales();
        $kept = [];

        foreach (array_values((array) $request->input('variants', [])) as $i => $row) {
            $name = Product::cleanTranslations((array) ($row['name'] ?? []), $locales);

            if ($name === [] || ! isset($row['price']) || $row['price'] === '') {
                continue;
            }

            $attrs = ['name' => $name, 'price' => $row['price'], 'sort' => $i, 'is_available' => ! empty($row['is_available'])];
            $existing = ! empty($row['id']) ? $product->variants()->whereKey((int) $row['id'])->first() : null;

            if ($existing) {
                $existing->update($attrs);
                $kept[] = $existing->id;
            } else {
                $kept[] = $product->variants()->create($attrs)->id;
            }
        }

        $product->variants()->whereNotIn('id', $kept)->delete();
        MenuCache::bump($product->restaurant_id);
    }

    /**
     * Saves the slots of a set menu. A combo with no usable slot (a slot needs a name and at least one dish) is simply a normal dish.
     * Slots are rewritten as a whole: guests pick from the live menu, so nothing refers to a slot id for long.
     */
    private function syncCombo(Product $product, Request $request): void
    {
        $locales = $request->user()->restaurant->menuLocales();
        $product->comboSlots()->delete(); // items go with their slot

        if ($request->boolean('is_combo')) {
            foreach (array_values((array) $request->input('combo.slots', [])) as $i => $row) {
                $name = Product::cleanTranslations((array) ($row['name'] ?? []), $locales);
                $items = collect((array) ($row['items'] ?? []))->filter(fn ($it) => ! empty($it['product_id']) && (int) $it['product_id'] !== $product->id)->unique('product_id')->values();

                if ($name === [] || $items->isEmpty()) {
                    continue;
                }

                $slot = $product->comboSlots()->create(['name' => $name, 'sort' => $i]);

                foreach ($items as $j => $it) {
                    $slot->items()->create(['product_id' => (int) $it['product_id'], 'price_delta' => $it['price_delta'] ?? 0, 'sort' => $j]);
                }
            }
        }

        MenuCache::bump($product->restaurant_id);
    }

    private function syncPairings(Product $product, Request $request): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('pairings', [])), fn ($id) => $id !== $product->id)));
        $product->pairings()->sync(collect($ids)->mapWithKeys(fn ($id, $i) => [$id => ['sort' => $i]])->all());
        MenuCache::bump($product->restaurant_id);
    }

    private function syncGroups(Product $product, Request $request): void
    {
        $ids = array_values(array_unique(array_map('intval', $request->input('option_groups', []))));
        $product->optionGroups()->sync(collect($ids)->mapWithKeys(fn ($id, $i) => [$id => ['sort' => $i]])->all());
        MenuCache::bump($product->restaurant_id); // pivot changes fire no model events
    }
}
