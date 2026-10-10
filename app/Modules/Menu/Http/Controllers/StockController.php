<?php

namespace App\Modules\Menu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Prices and portions for the whole menu on one screen, a percentage price change, and a CSV copy of the menu. */
class StockController extends Controller
{
    public function index(Request $request): View
    {
        return view('menu::stock.index', [
            'restaurant' => $request->user()->restaurant,
            'categories' => Category::with(['products' => fn ($q) => $q->orderBy('sort')->orderBy('id')])->orderBy('sort')->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'max:2000'],
            'rows.*.price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'rows.*.stock_qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'rows.*.low_stock_at' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'rows.*.is_available' => ['nullable', 'boolean'],
        ]);

        // Only this restaurant's products can be found (tenant scope), so foreign ids are simply ignored.
        $products = Product::whereIn('id', array_map('intval', array_keys($data['rows'])))->get();

        DB::transaction(function () use ($products, $data) {
            foreach ($products as $product) {
                $row = $data['rows'][$product->id];
                $tracked = isset($row['stock_qty']) && $row['stock_qty'] !== '';
                $product->update([
                    'price' => $row['price'],
                    'stock_qty' => $tracked ? (int) $row['stock_qty'] : null,
                    'low_stock_at' => $tracked && isset($row['low_stock_at']) && $row['low_stock_at'] !== '' ? (int) $row['low_stock_at'] : null,
                    'is_available' => ! empty($row['is_available']),
                ]);
            }
        });

        return back()->with('status', __('menu.stock_saved'));
    }

    /** Raises or lowers prices by a percentage. The old price is kept as the struck-through one only when it is a real discount. */
    public function adjust(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'percent' => ['required', 'numeric', 'between:-90,500', 'not_in:0'],
            'category_id' => ['nullable', 'integer'],
        ]);

        $count = 0;

        DB::transaction(function () use ($data, &$count) {
            Product::query()->when(! empty($data['category_id']), fn ($q) => $q->where('category_id', $data['category_id']))->get()->each(function (Product $p) use ($data, &$count) {
                $p->update(['price' => max(0, round((float) $p->price * (1 + (float) $data['percent'] / 100), 2))]);
                $count++;
            });
        });

        return back()->with('status', __('menu.adjust_done', ['count' => $count]));
    }

    public function export(Request $request): StreamedResponse
    {
        $restaurant = $request->user()->restaurant;

        return response()->streamDownload(function () use ($restaurant) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Category', 'Dish', 'Price', 'Stock', 'On sale today', 'Visible']);

            Product::with('category')->orderBy('category_id')->orderBy('sort')->chunk(500, function ($rows) use ($out, $restaurant) {
                foreach ($rows as $p) {
                    fputcsv($out, array_map(fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) ? "'".$v : $v, [
                        $p->category?->tr('name', null, $restaurant->locale), $p->tr('name', null, $restaurant->locale), number_format((float) $p->price, 2, '.', ''),
                        $p->stock_qty ?? '', $p->is_available ? 'yes' : 'no', $p->is_active ? 'yes' : 'no',
                    ]));
                }
            });
            fclose($out);
        }, 'menu-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
