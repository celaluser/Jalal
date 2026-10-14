<?php

namespace App\Modules\Menu\Services;

use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Storefront\Services\MenuCache;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Menu from a spreadsheet. The CSV is read and checked first (nothing is saved), shown as a preview, and only then applied.
 * Dishes are matched by category and name: a known dish gets its price, stock, description and availability updated, a new one is created.
 * Columns (any order, header names are not case sensitive): category, dish, description, price, stock, available, calories.
 */
class CsvMenuImport
{
    public const MAX_ROWS = 1000;

    public const MAX_BYTES = 1_048_576;

    public const COLUMNS = ['category', 'dish', 'description', 'price', 'stock', 'available', 'calories'];

    /** Header names people actually use, including those of this script's own export. */
    private const ALIASES = [
        'category' => ['category', 'kategori', 'section', 'group'], 'dish' => ['dish', 'name', 'item', 'product', 'urun', 'ürün', 'yemek'],
        'description' => ['description', 'aciklama', 'açıklama', 'desc'], 'price' => ['price', 'fiyat', 'cost'],
        'stock' => ['stock', 'stok', 'quantity', 'qty'], 'available' => ['available', 'on sale today', 'mevcut', 'in stock'],
        'calories' => ['calories', 'kcal', 'kalori'],
    ];

    public function __construct(private readonly LimitGuard $limits) {}

    /**
     * @return array{rows: list<array<string, mixed>>, errors: list<array{line: int, message: string}>, new_categories: int, new_products: int, updates: int}
     *
     * @throws InvalidArgumentException code: empty | too_big | too_many | header
     */
    public function parse(string $path, Restaurant $restaurant): array
    {
        if (filesize($path) > self::MAX_BYTES) {
            throw new InvalidArgumentException('too_big');
        }

        $text = $this->utf8((string) file_get_contents($path));
        $lines = preg_split('/\r\n|\n|\r/', trim($text));

        if (! $lines || trim($lines[0] ?? '') === '') {
            throw new InvalidArgumentException('empty');
        }

        $delimiter = $this->delimiter($lines[0]);
        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), str_getcsv($lines[0], $delimiter, '"', ''));
        $map = [];

        foreach (self::ALIASES as $column => $names) {
            foreach ($header as $i => $h) {
                if (in_array($h, $names, true)) {
                    $map[$column] = $i;
                    break;
                }
            }
        }

        if (! isset($map['category'], $map['dish'], $map['price'])) {
            throw new InvalidArgumentException('header');
        }

        if (count($lines) - 1 > self::MAX_ROWS) {
            throw new InvalidArgumentException('too_many');
        }

        $locale = $restaurant->locale ?: config('app.default_locale', 'en');
        $known = Product::with('category')->get()->mapWithKeys(fn (Product $p) => [$this->key($p->category?->tr('name', $locale, $locale), $p->tr('name', $locale, $locale)) => true]);
        $knownCategories = Category::all()->mapWithKeys(fn (Category $c) => [mb_strtolower($c->tr('name', $locale, $locale)) => true]);

        $rows = [];
        $errors = [];
        $seen = [];

        foreach (array_slice($lines, 1) as $n => $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv($line, $delimiter, '"', '');
            $get = fn (string $col) => isset($map[$col]) ? trim(strip_tags((string) ($cells[$map[$col]] ?? ''))) : '';
            $row = ['line' => $n + 2, 'category' => mb_substr($get('category'), 0, 120), 'dish' => mb_substr($get('dish'), 0, 160), 'description' => mb_substr($get('description'), 0, 1000)];
            $price = str_replace([' ', ','], ['', '.'], $get('price'));

            if ($row['category'] === '' || $row['dish'] === '') {
                $errors[] = ['line' => $row['line'], 'message' => 'missing_name'];

                continue;
            }

            if (! is_numeric($price) || (float) $price < 0 || (float) $price > 9999999) {
                $errors[] = ['line' => $row['line'], 'message' => 'bad_price'];

                continue;
            }

            $stock = $get('stock');

            if ($stock !== '' && (! ctype_digit($stock) || (int) $stock > 1000000)) {
                $errors[] = ['line' => $row['line'], 'message' => 'bad_stock'];

                continue;
            }

            $key = $this->key($row['category'], $row['dish']);

            if (isset($seen[$key])) {
                $errors[] = ['line' => $row['line'], 'message' => 'duplicate'];

                continue;
            }

            $seen[$key] = true;
            $calories = $get('calories');
            $available = mb_strtolower($get('available'));
            $row += [
                'price' => round((float) $price, 2), 'stock' => $stock === '' ? null : (int) $stock,
                'available' => $available === '' ? null : ! in_array($available, ['no', 'false', '0', 'hayır', 'hayir', 'n'], true),
                'calories' => ctype_digit($calories) && (int) $calories <= 65000 ? (int) $calories : null,
                'exists' => isset($known[$key]),
            ];
            $rows[] = $row;
        }

        $newCategories = collect($rows)->pluck('category')->unique(fn ($c) => mb_strtolower($c))->reject(fn ($c) => isset($knownCategories[mb_strtolower($c)]))->count();

        return [
            'rows' => $rows, 'errors' => $errors, 'new_categories' => $newCategories,
            'new_products' => collect($rows)->where('exists', false)->count(), 'updates' => collect($rows)->where('exists', true)->count(),
        ];
    }

    /**
     * Applies reviewed rows. Plan limits are checked for the whole batch first: it either fits or changes nothing.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, categories: int}
     *
     * @throws InvalidArgumentException code: limit_categories | limit_products
     */
    public function apply(Restaurant $restaurant, array $rows): array
    {
        $locale = $restaurant->locale ?: config('app.default_locale', 'en');
        $categories = Category::all()->mapWithKeys(fn (Category $c) => [mb_strtolower($c->tr('name', $locale, $locale)) => $c]);
        $products = Product::all()->mapWithKeys(fn (Product $p) => [$p->category_id.'|'.mb_strtolower($p->tr('name', $locale, $locale)) => $p]);

        $newCategories = collect($rows)->pluck('category')->unique(fn ($c) => mb_strtolower($c))->reject(fn ($c) => $categories->has(mb_strtolower($c)))->count();
        $newProducts = collect($rows)->filter(fn ($r) => ! ($categories[mb_strtolower($r['category'])] ?? null) || ! $products->has($categories[mb_strtolower($r['category'])]->id.'|'.mb_strtolower($r['dish'])))->count();

        if (! $this->limits->canAdd($restaurant, 'categories', Category::count(), $newCategories)) {
            throw new InvalidArgumentException('limit_categories');
        }

        if (! $this->limits->canAdd($restaurant, 'products', Product::count(), $newProducts)) {
            throw new InvalidArgumentException('limit_products');
        }

        $result = DB::transaction(function () use ($rows, $locale, $categories, $products) {
            $created = $updated = $madeCategories = 0;
            $categorySort = (int) Category::max('sort');

            foreach ($rows as $row) {
                $ck = mb_strtolower($row['category']);
                $category = $categories[$ck] ?? null;

                if (! $category) {
                    $category = $categories[$ck] = Category::create(['name' => [$locale => $row['category']], 'sort' => ++$categorySort, 'is_active' => true]);
                    $madeCategories++;
                }

                $pk = $category->id.'|'.mb_strtolower($row['dish']);
                $existing = $products[$pk] ?? null;

                $values = ['price' => $row['price']] + ($row['description'] !== '' ? ['description' => array_merge((array) ($existing?->description ?? []), [$locale => $row['description']])] : [])
                    + ($row['stock'] !== null ? ['stock_qty' => $row['stock']] : []) + ($row['available'] !== null ? ['is_available' => $row['available']] : [])
                    + ($row['calories'] !== null ? ['calories' => $row['calories']] : []);

                if ($existing) {
                    $existing->update($values);
                    $updated++;
                } else {
                    $products[$pk] = Product::create($values + ['category_id' => $category->id, 'name' => [$locale => $row['dish']], 'sort' => (int) Product::where('category_id', $category->id)->max('sort') + 1, 'is_active' => true]);
                    $created++;
                }
            }

            return ['created' => $created, 'updated' => $updated, 'categories' => $madeCategories];
        });

        MenuCache::bump($restaurant->id);

        return $result;
    }

    private function key(?string $category, ?string $dish): string
    {
        return mb_strtolower(trim((string) $category)).'|'.mb_strtolower(trim((string) $dish));
    }

    private function delimiter(string $header): string
    {
        return substr_count($header, ';') > substr_count($header, ',') ? ';' : (substr_count($header, "\t") > substr_count($header, ',') ? "\t" : ',');
    }

    /** UTF-8 as is; otherwise a spreadsheet's Windows-1254 (Turkish) or 1252 text is converted. */
    private function utf8(string $text): string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);

        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        return mb_convert_encoding($text, 'UTF-8', str_contains($text, "\xDD") || str_contains($text, "\xFD") || str_contains($text, "\xDE") || str_contains($text, "\xFE") ? 'Windows-1254' : 'Windows-1252');
    }
}
