<?php

namespace App\Modules\Menu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The menu workspace: categories on the left, the products of the selected one on the right. */
class MenuController extends Controller
{
    public function index(Request $request, LimitGuard $limits): View
    {
        $restaurant = $request->user()->restaurant;
        $categories = Category::withCount('products')->orderBy('sort')->orderBy('id')->get();
        $current = $categories->firstWhere('id', (int) $request->query('category')) ?? $categories->first();

        return view('menu::menu.index', [
            'restaurant' => $restaurant,
            'categories' => $categories,
            'current' => $current,
            'products' => $current ? Product::with(['image', 'category'])->where('category_id', $current->id)->orderBy('sort')->orderBy('id')->get() : collect(),
            'productLimit' => $limits->limit($restaurant, 'products'),
            'categoryLimit' => $limits->limit($restaurant, 'categories'),
            'productCount' => Product::count(),
            'toggles' => Product::TOGGLES,
        ]);
    }
}
