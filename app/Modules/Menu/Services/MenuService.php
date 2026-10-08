<?php

namespace App\Modules\Menu\Services;

use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Core\Models\Media;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Menu;
use App\Modules\Menu\Models\Option;
use App\Modules\Menu\Models\OptionGroup;
use App\Modules\Menu\Models\Product;
use App\Modules\Menu\Support\DishArt;
use App\Modules\Storefront\Services\MenuCache;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Menu rules that are not tied to a screen: plan limits, ordering, duplication, the customer-facing tree. */
class MenuService
{
    /** Sortable models by the key the reorder endpoint receives. */
    public const SORTABLE = ['categories' => Category::class, 'products' => Product::class, 'options' => Option::class, 'option-groups' => OptionGroup::class];

    public function __construct(private readonly LimitGuard $limits, private readonly TenantContext $tenant) {}

    /** @param 'categories'|'products' $key */
    public function canAdd(Restaurant $restaurant, string $key, int $adding = 1): bool
    {
        $count = $key === 'categories' ? Category::count() : Product::count();

        return $this->limits->canAdd($restaurant, $key, $count, $adding);
    }

    public function nextSort(string $modelClass, array $where = []): int
    {
        return (int) $modelClass::where($where)->max('sort') + 1;
    }

    /**
     * Persist a drag-and-drop order. Ids the current restaurant does not own are ignored, so a forged
     * request cannot reorder (or even probe) someone else's rows.
     *
     * @param  list<int>  $ids
     */
    public function reorder(string $type, array $ids): void
    {
        $class = self::SORTABLE[$type] ?? throw new InvalidArgumentException("Unknown sortable type [{$type}].");
        $owned = $class::whereIn('id', $ids)->pluck('id')->all();

        DB::transaction(function () use ($class, $ids, $owned) {
            foreach (array_values(array_filter($ids, fn ($id) => in_array($id, $owned))) as $position => $id) {
                $class::whereKey($id)->update(['sort' => $position + 1]);
            }
        });

        MenuCache::bump($this->tenant->id()); // query-builder updates fire no model events
    }

    /** A copy placed right after the original, hidden until the owner has reviewed it. */
    public function duplicate(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            $copy = $product->replicate();
            $copy->name = array_map(fn ($n) => $n.' (copy)', (array) $product->name);
            $copy->is_active = false;
            $copy->sort = $product->sort + 1;
            $copy->save();

            Product::where('category_id', $product->category_id)->where('sort', '>=', $copy->sort)->where('id', '!=', $copy->id)->increment('sort');
            foreach ($product->variants as $variant) {
                $copy->variants()->create($variant->only(['name', 'price', 'cost_price', 'sort', 'is_available']));
            }

            foreach ($product->comboSlots()->with('items')->get() as $slot) {
                $newSlot = $copy->comboSlots()->create(['name' => $slot->name, 'sort' => $slot->sort]);
                foreach ($slot->items as $item) {
                    $newSlot->items()->create(['product_id' => $item->product_id, 'price_delta' => $item->price_delta, 'sort' => $item->sort]);
                }
            }

            $copy->optionGroups()->sync($product->optionGroups->mapWithKeys(fn ($g) => [$g->id => ['sort' => $g->pivot->sort]])->all());
            MenuCache::bump($product->restaurant_id);

            return $copy;
        });
    }

    /**
     * What a customer sees: active categories with their active products and option groups.
     * Runs inside the restaurant's tenant context, so it also works from jobs and the public routes.
     *
     * @return list<array<string, mixed>>
     */
    public function tree(Restaurant $restaurant, ?string $locale = null): array
    {
        return $this->tenant->runAs($restaurant, function () use ($restaurant, $locale) {
            $default = $restaurant->locale;

            $menus = Menu::all()->keyBy('id');

            return Category::where('is_active', true)->orderBy('sort')->orderBy('id')
                ->with(['products' => fn ($q) => $q->where('is_active', true)->with(['image', 'variants', 'pairings:id', 'comboSlots.items.dish', 'optionGroups.options' => fn ($o) => $o->where('is_available', true)]), 'image'])
                ->get()
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'menu_id' => $c->menu_id && isset($menus[$c->menu_id]) ? $c->menu_id : 0,
                    'menu_name' => $c->menu_id && isset($menus[$c->menu_id]) ? $menus[$c->menu_id]->tr('name', $locale, $default) : null,
                    'menu_open' => $c->menu_id && isset($menus[$c->menu_id]) ? ['active' => $menus[$c->menu_id]->is_active, 'schedule' => $menus[$c->menu_id]->schedule] : null,
                    'name' => $c->tr('name', $locale, $default),
                    'description' => $c->tr('description', $locale, $default),
                    'image' => $c->image?->url(),
                    'icon' => $c->icon,
                    'schedule' => $c->schedule,
                    'products' => $c->products->map(fn (Product $p) => [
                        'id' => $p->id,
                        'name' => $p->tr('name', $locale, $default),
                        'description' => $p->tr('description', $locale, $default),
                        // With sizes the card shows "from" the smallest available price.
                        'price' => $p->variants->where('is_available', true)->isNotEmpty() ? (float) $p->variants->where('is_available', true)->min('price') : (float) $p->price,
                        'pairs' => $p->pairings->pluck('id')->all(),
                        'combo' => $p->is_combo && $p->comboSlots->isNotEmpty() ? $p->comboSlots->map(fn ($s) => ['id' => $s->id, 'name' => $s->tr('name', $locale, $default), 'items' => $s->items->filter(fn ($i) => $i->dish && $i->dish->is_active)->map(fn ($i) => ['id' => $i->product_id, 'name' => $i->dish->tr('name', $locale, $default), 'delta' => (float) $i->price_delta, 'available' => $i->dish->canBeOrdered()])->values()->all()])->values()->all() : null,
                        'variants' => $p->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->tr('name', $locale, $default), 'price' => (float) $v->price, 'available' => $v->is_available])->values()->all(),
                        'compare_price' => $p->isOnSale() ? (float) $p->compare_price : null,
                        'image' => $p->image?->thumbUrl(),
                        'image_full' => $p->image?->url(),
                        // Illustration shown when the dish has no photo of its own.
                        'art' => DishArt::url($p->tr('name', $locale, $default), $c->tr('name', $locale, $default)),
                        'available' => $p->canBeOrdered(),
                        'featured' => $p->is_featured,
                        'calories' => $p->calories,
                        'schedule' => $p->schedule,
                        'order_types' => $p->order_types,
                        'limited_until' => $p->limited_until?->toDateString(),
                        'badges' => $p->badges ?? [],
                        'nutrition' => $p->nutrition,
                        'portion_size' => $p->portion_size,
                        'spice' => (int) $p->spice_level,
                        'video' => ProductMedia::video($p->video_url),
                        'gallery' => $p->gallery ? Media::whereIn('id', $p->gallery)->get()->sortBy(fn ($m) => array_search($m->id, $p->gallery))->map(fn ($m) => ['thumb' => $m->thumbUrl(), 'full' => $m->url()])->values()->all() : [],
                        'prep_minutes' => $p->prep_minutes,
                        'allergens' => $p->allergens ?? [],
                        'dietary' => $p->dietary ?? [],
                        'option_groups' => $p->optionGroups->map(fn (OptionGroup $g) => [
                            'id' => $g->id,
                            'name' => $g->tr('name', $locale, $default),
                            'type' => $g->type,
                            'required' => $g->is_required,
                            'max_select' => $g->max_select,
                            'options' => $g->options->map(fn (Option $o) => [
                                'id' => $o->id, 'name' => $o->tr('name', $locale, $default),
                                'price_delta' => (float) $o->price_delta, 'default' => $o->is_default,
                            ])->values()->all(),
                        ])->values()->all(),
                    ])->values()->all(),
                ])->filter(fn ($c) => $c['products'] !== [])->values()->all();
        });
    }
}
