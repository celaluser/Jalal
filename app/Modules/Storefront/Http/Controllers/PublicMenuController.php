<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Services\ThemeRegistry;
use App\Modules\Storefront\Services\CartPricing;
use App\Modules\Storefront\Services\MenuCache;
use App\Modules\Storefront\Services\MenuLocale;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tables\Services\TableService;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** The page a guest sees after scanning a QR code. */
class PublicMenuController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly MenuCache $cache,
        private readonly MenuLocale $locales,
        private readonly ThemeRegistry $themes,
        private readonly LimitGuard $limits,
    ) {}

    public function show(Request $request): View|Response
    {
        return $this->render($request, $this->tenant->get());
    }

    /** Render the menu for a restaurant (also used by the landing route on restaurant domains). */
    public function render(Request $request, Restaurant $restaurant): View|Response
    {
        $locale = $this->locales->resolve($request, $restaurant);
        app()->setLocale($locale);

        // An expired or missing subscription takes the menu offline politely, without a 404 that search engines would remember.
        if ($this->limits->plan($restaurant) === null) {
            return response()->view('storefront::unavailable', $this->shared($restaurant, $locale), 503)->header('Retry-After', '3600');
        }

        $table = $this->currentTable($request, $restaurant);
        $tree = $this->cache->tree($restaurant, $locale);

        $response = response()->view('storefront::menu', $this->shared($restaurant, $locale) + [
            'tree' => $tree,
            'table' => $table ? ['id' => $table->id, 'name' => $table->name] : null,
            'currency' => $this->currency($restaurant),
            'base' => rtrim($request->getPathInfo(), '/'),
            'allergens' => config('menu.allergens'),
            'dietary' => config('menu.dietary'),
            'description' => __('customer.meta_description', ['name' => $restaurant->name]),
            // A table link is personal to that table's guests: keep it out of search results.
            'noindex' => $table !== null,
        ]);

        // Remember a language the guest explicitly picked (and only a valid one).
        return $request->query('lang') === $locale ? $response->cookie(MenuLocale::COOKIE, $locale, 60 * 24 * 180) : $response;
    }

    /** Root of a restaurant's own domain: resolved by the landing route, which hands over here. */
    public function host(Request $request, Restaurant $restaurant): View|Response
    {
        abort_if($restaurant->isSuspended(), 404);

        $this->tenant->set($restaurant);

        return $this->render($request, $restaurant);
    }

    /** A scanned table QR code: remember the table for this visit and open the menu. */
    public function table(Request $request, TableService $tables): RedirectResponse
    {
        // Read by name: on /r/{restaurant}/t/{token} Laravel would pass the slug first if it were a method argument.
        $token = (string) $request->route('token');
        $restaurant = $this->tenant->get();
        $table = $tables->findByToken($token);

        if ($table) {
            $request->session()->put("table.{$restaurant->id}", $table->id);
        }

        // Stay on the host the guest scanned from: the table is remembered in this host's session.
        $url = $request->route('restaurant') !== null ? url('/'.config('tenancy.path_prefix').'/'.$restaurant->slug) : url('/');
        $lang = $request->query('lang');

        return redirect()->to($url.($lang ? '?lang='.urlencode((string) $lang) : ''))->with($table ? [] : ['table_invalid' => true]);
    }

    /** Server-side pricing of the browser's cart. */
    public function quote(Request $request, CartPricing $pricing): JsonResponse
    {
        $restaurant = $this->tenant->get();
        abort_if($this->limits->plan($restaurant) === null, 503);

        $data = $request->validate(['lines' => ['required', 'array', 'max:'.CartPricing::MAX_LINES]]);
        app()->setLocale($this->locales->resolve($request, $restaurant));

        return response()->json($pricing->quote($restaurant, $data['lines']));
    }

    private function currentTable(Request $request, Restaurant $restaurant)
    {
        $id = $request->session()->get("table.{$restaurant->id}");

        return $id ? DiningTable::where('id', $id)->where('is_active', true)->first() : null;
    }

    /** @return array<string, mixed> */
    private function shared(Restaurant $restaurant, string $locale): array
    {
        return [
            'restaurant' => $restaurant,
            'locale' => $locale,
            'dir' => $this->locales->isRtl($locale) ? 'rtl' : 'ltr',
            'themeCss' => $this->themes->css($restaurant),
            'settings' => $this->themes->settings($restaurant),
            'languages' => $this->locales->names($restaurant),
            'logo' => $restaurant->logo?->url(),
        ];
    }

    /** @return array{symbol: string, after: bool, decimals: int, decimal: string, thousands: string} */
    private function currency(Restaurant $restaurant): array
    {
        $c = $restaurant->currency_code ? Currency::where('code', $restaurant->currency_code)->first() : null;

        return [
            'symbol' => $c->symbol ?? '', 'after' => ($c->symbol_position ?? 'before') === 'after', 'decimals' => (int) ($c->decimals ?? 2),
            'decimal' => $c->decimal_separator ?? '.', 'thousands' => $c->thousands_separator ?? ',',
        ];
    }
}
