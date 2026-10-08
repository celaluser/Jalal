<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Branches\Services\BranchMenu;
use App\Modules\Branches\Services\GuestBranch;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Models\Banner;
use App\Modules\Marketing\Models\PromoCode;
use App\Modules\Marketing\Services\MarketingSettings;
use App\Modules\Marketing\Services\PromoService;
use App\Modules\Marketing\Services\ReviewService;
use App\Modules\Menu\Services\MenuAvailability;
use App\Modules\Menu\Services\ThemeRegistry;
use App\Modules\Messaging\Services\Messenger;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\DeliveryZone;
use App\Modules\Orders\Services\DeliveryZones;
use App\Modules\Orders\Services\OrderSettings;
use App\Modules\Orders\Services\OrderTotals;
use App\Modules\Orders\Services\PushNotifier;
use App\Modules\Orders\Services\WaitEstimate;
use App\Modules\Orders\Support\OrderType;
use App\Modules\Reservations\Services\ReservationSettings;
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

        // A restaurant with several branches: find out which one the guest is at before showing prices.
        $guestBranch = app(GuestBranch::class);
        $branch = $guestBranch->resolve($request, $restaurant, $table);

        if (! $branch && ($branches = $guestBranch->active())->count() > 1) {
            return response()->view('storefront::branches', $this->shared($restaurant, $locale) + ['branches' => $branches, 'base' => rtrim($request->getPathInfo(), '/') ?: '/'])->header('Cache-Control', 'no-store');
        }

        $tree = app(MenuAvailability::class)->filter($this->cache->tree($restaurant, $locale), $restaurant);
        $tree = app(BranchMenu::class)->apply($tree, $branch?->id);

        $response = response()->view('storefront::menu', $this->shared($restaurant, $locale) + [
            'tree' => $tree,
            'menus' => app(MenuAvailability::class)->menusOf($tree, __('customer.main_menu')),
            'table' => $table ? ['id' => $table->id, 'name' => $table->name] : null,
            'currency' => $this->currency($restaurant),
            'base' => rtrim($request->getPathInfo(), '/'),
            'allergens' => config('menu.allergens'),
            'dietary' => config('menu.dietary'),
            'description' => __('customer.meta_description', ['name' => $restaurant->name]),
            // A table link is personal to that table's guests: keep it out of search results.
            'noindex' => $table !== null || $request->boolean('kiosk'),
            // Self-order kiosk: a screen in the restaurant where guests order for themselves (?kiosk=1).
            'kiosk' => $request->boolean('kiosk'),
            'ordering' => $this->ordering($restaurant, $table, $request),
            'rating' => $this->rating($restaurant),
            'reserveUrl' => app(ReservationSettings::class)->active($restaurant) ? rtrim($request->getPathInfo(), '/').'/reserve' : null,
            'banners' => $this->banners($restaurant, $locale),
            'account' => ($c = AccountController::current($request, $restaurant->id)) ? ['name' => $c->name, 'phone' => $c->phone, 'email' => $c->email] : null,
            'currencies' => $this->currencies($restaurant),
            'branch' => $branch ? ['name' => $branch->name, 'open' => $branch->isOpen(), 'switch' => $guestBranch->active()->count() > 1] : null,
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

        $data = $request->validate([
            'lines' => ['required', 'array', 'max:'.CartPricing::MAX_LINES], 'type' => ['nullable', 'in:'.implode(',', OrderType::ALL)],
            'promo_code' => ['nullable', 'string', 'max:40'], 'customer_email' => ['nullable', 'string', 'max:190'], 'customer_phone' => ['nullable', 'string', 'max:40'],
            'delivery_zone' => ['nullable', 'integer'],
        ]);
        app()->setLocale($this->locales->resolve($request, $restaurant));

        $quote = $pricing->quote($restaurant, $data['lines'], $data['type'] ?? null, app(GuestBranch::class)->id($request, $restaurant));

        // With an order type chosen, also show what the order will cost in full.
        if (! empty($data['type'])) {
            $discount = 0;

            // A promo code is checked here exactly as the order will check it, so the guest sees the real price.
            if (! empty($data['promo_code']) && $quote['valid']) {
                try {
                    $applied = app(PromoService::class)->apply($restaurant, $data['promo_code'], $quote['subtotal_cents'], $data['customer_email'] ?? null, $data['customer_phone'] ?? null);
                    $discount = $applied['discount_cents'];
                    $quote['promo'] = ['valid' => true, 'code' => $applied['promo']->code, 'message' => __('marketing.promo_applied', ['code' => $applied['promo']->code, 'amount' => $restaurant->money($discount / 100)])];
                } catch (OrderException $e) {
                    $quote['promo'] = ['valid' => false, 'code' => PromoCode::normalize($data['promo_code']), 'message' => __('orders.error_'.$e->reason)];
                }
            }

            // A delivery zone changes the fee; until the guest picks one, the fee shows as zero.
            $zones = app(DeliveryZones::class);
            $zone = $data['type'] === OrderType::DELIVERY && ! empty($data['delivery_zone']) ? DeliveryZone::where('is_active', true)->find($data['delivery_zone']) : null;
            $settings = $zones->apply(app(OrderSettings::class)->for($restaurant), $zone);
            $quote['delivery_min'] = $data['type'] === OrderType::DELIVERY ? (int) round((float) $settings['delivery_min'] * 100) : 0;
            $sums = app(OrderTotals::class)->compute($quote['subtotal_cents'], $data['type'], $settings, $discount, (int) collect($quote['lines'])->sum('qty'));
            $quote['totals'] = collect($sums)->map(fn ($c) => $restaurant->money($c / 100))->all() + ['raw' => $sums];
        }

        return response()->json($quote);
    }

    /** Average rating shown under the name, once enough guests have rated (a lone review is not a rating). @return array{average: float, count: int}|null */
    private function rating(Restaurant $restaurant): ?array
    {
        if (! app(MarketingSettings::class)->get($restaurant, 'show_rating')) {
            return null;
        }

        $summary = $this->tenant->runAs($restaurant, fn () => app(ReviewService::class)->summary(public: true));

        return $summary['count'] >= 3 ? ['average' => $summary['average'], 'count' => $summary['count']] : null;
    }

    /** What the checkout needs to know about this restaurant's ordering rules. @return array<string, mixed> */
    private function ordering(Restaurant $restaurant, $table, Request $request): array
    {
        $settings = app(OrderSettings::class);
        $s = $settings->for($restaurant);
        $pick = ! $table && $s['dine_in_pick_table'];

        return [
            'accepting' => $settings->accepting($restaurant),
            'message' => $s['paused_message'] ?: __('orders.error_closed'),
            'types' => $settings->types($restaurant),
            'table' => $table ? ['id' => $table->id, 'name' => $table->name, 'label' => table_label($table->name)] : null,
            'tables' => $pick ? DiningTable::where('is_active', true)->orderBy('sort')->orderBy('id')->get(['id', 'name'])->map(fn ($t) => ['id' => $t->id, 'name' => table_label($t->name)])->all() : [],
            'payments' => $settings->paymentMethods($restaurant),
            'requireName' => (bool) $s['require_name'],
            'deliveryMin' => $settings->cents($restaurant, 'delivery_min') > 0 ? $restaurant->money($settings->cents($restaurant, 'delivery_min') / 100) : null,
            'taxIncluded' => (bool) $s['prices_include_tax'],
            'promos' => PromoCode::where('is_active', true)->exists() || (bool) app(MarketingSettings::class)->get($restaurant, 'loyalty_enabled'),
            'orderUrl' => rtrim($request->getPathInfo(), '/').'/order',
            'wait' => app(WaitEstimate::class)->for($restaurant)['minutes'],
            'maxItems' => (int) $s['max_items'],
            'zones' => app(DeliveryZones::class)->active()->map(fn ($z) => ['id' => $z->id, 'name' => $z->name, 'fee' => $restaurant->money((float) $z->fee), 'min' => (float) $z->min_order > 0 ? $restaurant->money((float) $z->min_order) : null, 'eta' => $z->eta_minutes])->all(),
            'schedule' => $s['schedule_orders'] ? ['lead' => (int) $s['schedule_lead'], 'days' => (int) $s['schedule_days'], 'tz' => $restaurant->timezone ?: 'UTC'] : null,
            // Ways a guest can ask to be told when the order is ready. SMS/WhatsApp only if a provider is set up.
            'notify' => array_values(array_filter([
                $s['notify_sms'] && app(Messenger::class)->available('sms') ? 'sms' : null,
                $s['notify_whatsapp'] && app(Messenger::class)->available('whatsapp') ? 'whatsapp' : null,
                $s['notify_push'] && app(PushNotifier::class)->configured() ? 'push' : null,
            ])),
            'pushKey' => $s['notify_push'] ? app(PushNotifier::class)->publicKey() : null,
            'requestUrl' => $table ? rtrim($request->getPathInfo(), '/').'/request' : null,
            'tabUrl' => $table ? rtrim($request->getPathInfo(), '/').'/tab' : null,
        ];
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

    /** Live banners and pop-ups for the guest menu, in the guest's language. @return list<array<string, mixed>> */
    private function banners(Restaurant $restaurant, string $locale): array
    {
        $today = app(MenuAvailability::class)->now($restaurant)->startOfDay();

        return Banner::with('image')->live($today)->orderBy('sort')->orderBy('id')->limit(8)->get()->map(fn (Banner $b) => [
            'id' => $b->id, 'title' => $b->tr('title', $locale, $restaurant->locale), 'text' => $b->tr('text', $locale, $restaurant->locale) ?: null,
            'button' => $b->tr('button', $locale, $restaurant->locale) ?: null, 'link' => $b->link_url, 'image' => $b->image?->url(), 'popup' => $b->is_popup,
            // A changed banner shows its pop-up again.
            'stamp' => $b->updated_at?->timestamp,
        ])->all();
    }

    /**
     * Other currencies a guest can view prices in, when the restaurant allows it and the admin set rates.
     *
     * @return list<array<string, mixed>>
     */
    private function currencies(Restaurant $restaurant): array
    {
        if (! $this->themes->settings($restaurant)['currency_switch']) {
            return [];
        }

        $base = Currency::where('code', $restaurant->currency_code)->first();

        if (! $base || ! $base->rate) {
            return [];
        }

        return Currency::where('is_active', true)->whereNotNull('rate')->where('rate', '>', 0)->orderBy('code')->get()->map(fn (Currency $c) => [
            'code' => $c->code, 'symbol' => $c->symbol, 'after' => $c->symbol_position === 'after', 'decimals' => (int) $c->decimals,
            'decimal' => $c->decimal_separator, 'thousands' => $c->thousands_separator, 'factor' => (float) $c->rate / (float) $base->rate, 'base' => $c->code === $base->code,
        ])->all();
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
