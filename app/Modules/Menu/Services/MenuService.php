<?php

namespace App\Modules\Menu\Services;

use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
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

            return Category::where('is_active', true)->orderBy('sort')->orderBy('id')
                ->with(['products' => fn ($q) => $q->where('is_active', true)->with(['image', 'optionGroups.options' => fn ($o) => $o->where('is_available', true)]), 'image'])
                ->get()
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name' => $c->tr('name', $locale, $default),
                    'description' => $c->tr('description', $locale, $default),
                    'image' => $c->image?->url(),
                    'products' => $c->products->map(fn (Product $p) => [
                        'id' => $p->id,
                        'name' => $p->tr('name', $locale, $default),
                        'description' => $p->tr('description', $locale, $default),
                        'price' => (float) $p->price,
                        'compare_price' => $p->isOnSale() ? (float) $p->compare_price : null,
                        'image' => $p->image?->thumbUrl(),
                        'image_full' => $p->image?->url(),
                        // Illustration shown when the dish has no photo of its own.
                        'art' => DishArt::url($p->tr('name', $locale, $default), $c->tr('name', $locale, $default)),
                        'available' => $p->canBeOrdered(),
                        'featured' => $p->is_featured,
                        'calories' => $p->calories,
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
