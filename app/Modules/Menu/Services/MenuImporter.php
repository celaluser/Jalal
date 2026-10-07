<?php

namespace App\Modules\Menu\Services;

use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates categories and dishes from an already reviewed list (the AI import preview, later CSV).
 * Plan limits are checked for the whole batch first, so an import either fits or changes nothing.
 */
class MenuImporter
{
    public function __construct(private readonly LimitGuard $limits) {}

    /**
     * @param  list<array{name: string, items: list<array{name: string, description?: string, price?: float|int|string|null}>}>  $categories
     * @return array{categories: int, products: int, hidden: int}
     *
     * @throws InvalidArgumentException reason is "limit_categories" or "limit_products"
     */
    public function import(Restaurant $restaurant, array $categories): array
    {
        $locale = $restaurant->locale ?: config('app.default_locale', 'en');
        $existing = Category::all()->mapWithKeys(fn (Category $c) => [mb_strtolower($c->tr('name', $locale, $locale)) => $c]);

        $newCategories = collect($categories)->filter(fn ($c) => ! $existing->has(mb_strtolower(trim($c['name']))))->unique(fn ($c) => mb_strtolower(trim($c['name'])))->count();
        $newProducts = collect($categories)->sum(fn ($c) => count($c['items']));

        if (! $this->limits->canAdd($restaurant, 'categories', Category::count(), $newCategories)) {
            throw new InvalidArgumentException('limit_categories');
        }

        if (! $this->limits->canAdd($restaurant, 'products', Product::count(), $newProducts)) {
            throw new InvalidArgumentException('limit_products');
        }

        return DB::transaction(function () use ($categories, $existing, $locale) {
            $madeCategories = 0;
            $madeProducts = 0;
            $hidden = 0;
            $sort = (int) Category::max('sort');

            foreach ($categories as $row) {
                $key = mb_strtolower(trim($row['name']));
                $category = $existing[$key] ?? null;

                if (! $category) {
                    $category = Category::create(['name' => [$locale => trim($row['name'])], 'sort' => ++$sort, 'is_active' => true]);
                    $existing[$key] = $category;
                    $madeCategories++;
                }

                $position = (int) Product::where('category_id', $category->id)->max('sort');

                foreach ($row['items'] as $item) {
                    $price = isset($item['price']) && is_numeric($item['price']) ? round((float) $item['price'], 2) : null;

                    // A dish without a price stays hidden until the owner fills it in.
                    Product::create([
                        'category_id' => $category->id, 'name' => [$locale => trim($item['name'])],
                        'description' => ($item['description'] ?? '') !== '' ? [$locale => $item['description']] : null,
                        'price' => $price ?? 0, 'sort' => ++$position, 'is_active' => $price !== null,
                    ]);
                    $madeProducts++;
                    $hidden += $price === null ? 1 : 0;
                }
            }

            return ['categories' => $madeCategories, 'products' => $madeProducts, 'hidden' => $hidden];
        });
    }
}
