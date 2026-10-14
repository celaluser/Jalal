<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Models\Banner;
use App\Modules\Menu\Services\MenuAvailability;
use App\Modules\Menu\Services\ThemeRegistry;
use App\Modules\Storefront\Services\MenuCache;
use App\Modules\Storefront\Services\MenuLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Digital signage: a full-screen menu that rotates by itself, for a TV in the restaurant.
 * Open /r/{slug}/tv on the screen's browser (?s=seconds per slide, ?lang=xx, ?per=dishes per slide).
 */
class TvMenuController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly MenuCache $cache,
        private readonly MenuLocale $locales,
        private readonly ThemeRegistry $themes,
        private readonly LimitGuard $limits,
    ) {}

    public function show(Request $request): Response
    {
        $restaurant = $this->tenant->get();
        $locale = $this->locales->resolve($request, $restaurant);
        app()->setLocale($locale);

        abort_if($this->limits->plan($restaurant) === null, 404);

        $tree = app(MenuAvailability::class)->filter($this->cache->tree($restaurant, $locale), $restaurant);
        $per = max(3, min(12, (int) $request->query('per', 6)));
        $seconds = max(5, min(60, (int) $request->query('s', 10)));

        $slides = [];

        // Offers first: dishes on sale, then the owner's own banners.
        $sale = collect($tree)->flatMap(fn ($c) => $c['products'])->filter(fn ($p) => $p['available'] && $p['compare_price'])->take(6)->values()->all();

        if ($sale !== []) {
            $slides[] = ['kind' => 'sale', 'title' => __('tv.offers'), 'products' => $sale];
        }

        $today = app(MenuAvailability::class)->now($restaurant)->startOfDay();
        $banners = $this->tenant->runAs($restaurant, fn () => Banner::with('image')->live($today)->orderBy('sort')->orderBy('id')->limit(6)->get());

        foreach ($banners as $b) {
            $slides[] = ['kind' => 'banner', 'title' => $b->tr('title', $locale, $restaurant->locale), 'text' => $b->tr('text', $locale, $restaurant->locale), 'image' => $b->image?->url()];
        }

        foreach ($tree as $category) {
            $products = collect($category['products'])->filter(fn ($p) => $p['available'])->values();

            foreach ($products->chunk($per) as $i => $chunk) {
                $slides[] = ['kind' => 'menu', 'title' => $category['name'], 'page' => $i + 1, 'pages' => (int) ceil($products->count() / $per), 'products' => $chunk->all()];
            }
        }

        return response()->view('storefront::tv', [
            'restaurant' => $restaurant, 'locale' => $locale, 'dir' => $this->locales->isRtl($locale) ? 'rtl' : 'ltr',
            'themeCss' => $this->themes->css($restaurant), 'logo' => $restaurant->logo?->url(),
            'slides' => $slides, 'seconds' => $seconds,
        ])->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }
}
