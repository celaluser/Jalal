<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Branches\Services\GuestBranch;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Services\LoyaltyService;
use App\Modules\Marketing\Services\MarketingSettings;
use App\Modules\Marketing\Services\ReviewService;
use App\Modules\Menu\Services\ThemeRegistry;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\OrderSettings;
use App\Modules\Orders\Services\PaymentLedger;
use App\Modules\Orders\Services\PushNotifier;
use App\Modules\Orders\Services\RestaurantGateways;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Orders\Support\OrderType;
use App\Modules\Storefront\Services\MenuLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** What a guest does with an order: place it, follow it, cancel it while it is still new. */
class CustomerOrderController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly OrderService $orders,
        private readonly OrderSettings $settings,
        private readonly MenuLocale $locales,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $restaurant = $this->tenant->get();
        $locale = $this->locales->resolve($request, $restaurant);
        app()->setLocale($locale);

        $data = $request->validate([
            'type' => ['required', Rule::in(OrderType::ALL)],
            'lines' => ['required', 'array', 'max:50'],
            'table_id' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:80'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'customer_email' => ['nullable', 'string', 'max:190'],
            'marketing_opt_in' => ['nullable', 'boolean'],
            'promo_code' => ['nullable', 'string', 'max:40'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'vehicle' => ['nullable', 'string', 'max:80'],
            'room' => ['nullable', 'string', 'max:30'],
            'scheduled_for' => ['nullable', 'date'],
            'notify' => ['nullable', Rule::in(['sms', 'whatsapp', 'push'])],
            'note' => ['nullable', 'string', 'max:300'],
            'payment_method' => ['required', Rule::in(['cash', 'card', 'online'])],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'kiosk' => ['nullable', 'boolean'],
        ]);
        $kiosk = $request->boolean('kiosk');

        // A kiosk takes orders for eating in or taking away; there is nobody to deliver to a screen in the shop.
        if ($kiosk && $data['type'] === OrderType::DELIVERY) {
            return response()->json(['error' => 'type_unavailable', 'message' => __('orders.error_type_unavailable')], 422);
        }

        // A guest who scanned a table code orders for that table; they cannot claim another one.
        $scanned = $request->session()->get("table.{$restaurant->id}");

        if ($data['type'] === OrderType::DINE_IN) {
            $data['table_id'] = $scanned ?: ($this->settings->for($restaurant)['dine_in_pick_table'] ? ($data['table_id'] ?? null) : null);
        }

        $data['branch_id'] = app(GuestBranch::class)->id($request, $restaurant);

        try {
            $order = $this->orders->place($restaurant, $data + ['locale' => $locale], $kiosk ? 'kiosk' : 'qr');
        } catch (OrderException $e) {
            return response()->json(['error' => $e->reason, 'message' => __('orders.error_'.$e->reason), 'lines' => $e->details], 422);
        }

        // Remember the order in this browser so the guest can come back to its status page.
        return response()->json(['number' => $order->number, 'token' => $order->token, 'url' => $this->statusUrl($request, $order).($order->payment_method === 'online' ? '?pay=1' : '')], 201);
    }

    public function show(Request $request): View
    {
        $restaurant = $this->tenant->get();
        $order = $this->find($request);
        app()->setLocale($this->locales->resolve($request, $restaurant));

        return view('orders::customer.status', [
            'restaurant' => $restaurant,
            'order' => $order->load('items'),
            'menuUrl' => $this->menuUrl($request),
            'themeCss' => app(ThemeRegistry::class)->css($restaurant),
            'dir' => $this->locales->isRtl(app()->getLocale()) ? 'rtl' : 'ltr',
            'locale' => app()->getLocale(),
            'state' => $this->state($order),
            'statusUrl' => $this->statusUrl($request, $order).'/status',
            'cancelUrl' => $this->statusUrl($request, $order).'/cancel',
            'canCancel' => $this->canCancel($order),
            'reviewUrl' => $this->statusUrl($request, $order).'/review',
            'pushUrl' => $this->statusUrl($request, $order).'/push',
            'payUrl' => $this->statusUrl($request, $order).'/pay',
            'receiptUrl' => $this->statusUrl($request, $order).'/receipt',
            'payResult' => session('pay_result'),
            'autoPay' => $request->boolean('pay'),
            'locale_tag' => str_replace('_', '-', app()->getLocale()),
            'workerUrl' => $this->menuUrl($request).'/sw.js',
            'reorderUrl' => $this->menuUrl($request).'?reorder='.$order->token,
            'money' => fn (int $cents) => $restaurant->money($cents / 100),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        app()->setLocale($this->locales->resolve($request, $this->tenant->get()));

        return response()->json($this->state($this->find($request)))->header('Cache-Control', 'no-store');
    }

    public function cancel(Request $request): JsonResponse
    {
        $order = $this->find($request);

        if (! $this->canCancel($order)) {
            return response()->json(['error' => 'cannot_cancel', 'message' => __('orders.error_cannot_cancel')], 422);
        }

        $this->orders->transition($order, OrderStatus::CANCELLED, null, __('orders.cancelled_by_guest'));

        return response()->json($this->state($order->refresh()));
    }

    /** What the status page needs, and nothing personal (no phone, no address). */
    private function state(Order $order): array
    {
        $order->loadMissing('items');

        return [
            'number' => $order->number, 'status' => $order->status, 'type' => $order->type, 'open' => $order->isOpen(),
            'steps' => OrderStatus::FLOW, 'prep_minutes' => $order->prep_minutes,
            'eta' => $order->accepted_at && $order->isOpen() ? $order->accepted_at->copy()->addMinutes((int) $order->prep_minutes)->toIso8601String() : null,
            'paid' => $order->isPaid(), 'can_cancel' => $this->canCancel($order),
            'pay' => $this->payState($order), 'paid_cents' => $order->paid_cents, 'tip' => $order->tip_cents > 0 ? $order->restaurant->money($order->tip_cents / 100) : null,
            'dispatched' => $order->dispatched_at !== null, 'scheduled' => $order->scheduled_for ? $order->scheduled_for->setTimezone($order->restaurant->timezone ?: 'UTC')->isoFormat('ddd, LT') : null,
            'vehicle' => $order->vehicle, 'room' => $order->room,
            'packaging' => $order->packaging_cents > 0 ? $order->restaurant->money($order->packaging_cents / 100) : null,
            // Offer browser notifications while the order is open and the guest did not choose a channel yet.
            'push_key' => $order->isOpen() && $order->notify_channel !== 'push' && ($this->settings->for($order->restaurant)['notify_push'] ?? false) ? app(PushNotifier::class)->publicKey() : null,
            'items' => $order->items->map(fn ($i) => ['name' => $i->name, 'qty' => $i->qty, 'options' => $i->optionsLabel(), 'total' => $order->restaurant->money($i->total_cents / 100)])->all(),
            'total' => $order->restaurant->money($order->total_cents / 100),
            'discount' => $order->discount_cents ? ['code' => $order->promo_code, 'amount' => $order->restaurant->money($order->discount_cents / 100)] : null,
        ] + $this->afterMeal($order);
    }

    /** What the "pay now" box needs: what is owed, the gateways to pay with, and the tab if the table has one. Null when nothing can be paid online. @return array<string, mixed>|null */
    private function payState(Order $order): ?array
    {
        $restaurant = $order->restaurant;
        $ledger = app(PaymentLedger::class);
        $remaining = $ledger->remaining($order);
        $gateways = app(RestaurantGateways::class)->availableFor($restaurant);

        if ($order->status === OrderStatus::CANCELLED || $remaining < 1 || $gateways === []) {
            return null;
        }

        $tab = $order->tab_id ? Order::where('tab_id', $order->tab_id)->whereNull('paid_at')->where('status', '!=', OrderStatus::CANCELLED)->get() : collect();
        $tabCents = (int) $tab->sum(fn (Order $o) => $ledger->remaining($o));

        return [
            'remaining_cents' => $remaining, 'remaining' => $restaurant->money($remaining / 100), 'currency' => $restaurant->currency_code,
            'gateways' => collect($gateways)->map(fn ($g) => ['code' => $g->code(), 'name' => $g->name()])->values()->all(),
            'tips' => OrderPaymentController::TIP_PRESETS,
            'tab' => $tab->count() > 1 ? ['count' => $tab->count(), 'cents' => $tabCents, 'remaining' => $restaurant->money($tabCents / 100)] : null,
        ];
    }

    /** Loyalty reward and feedback form, offered once the meal is done. @return array<string, mixed> */
    private function afterMeal(Order $order): array
    {
        if ($order->status !== OrderStatus::COMPLETED) {
            return ['reward' => null, 'review' => null];
        }

        $restaurant = $order->restaurant;
        $loyalty = app(LoyaltyService::class);
        $reviews = app(ReviewService::class);
        $reward = $loyalty->rewardFor($order);
        $review = $reviews->forOrder($order);

        return [
            'reward' => $reward ? ['code' => $reward->code, 'text' => $loyalty->describe($restaurant, $reward), 'until' => $reward->ends_at?->toFormattedDateString()] : null,
            'review' => ['open' => $reviews->canReview($restaurant, $order), 'rating' => $review?->rating, 'reply' => $review?->reply,
                // Happy guests are invited to review the restaurant publicly too (the link is the owner's own).
                'redirect' => $review && $review->rating >= (int) app(MarketingSettings::class)->get($restaurant, 'review_min') && ($url = (string) app(MarketingSettings::class)->get($restaurant, 'review_url')) !== '' ? $url : null],
        ];
    }

    private function canCancel(Order $order): bool
    {
        return $order->status === OrderStatus::NEW && (bool) $this->settings->for($order->restaurant)['allow_cancel'];
    }

    /** The token is read by name: on /r/{restaurant}/order/{token} Laravel would hand the slug to a method argument first. */
    private function find(Request $request): Order
    {
        return Order::with('restaurant')->where('token', (string) $request->route('token'))->firstOrFail();
    }

    private function statusUrl(Request $request, Order $order): string
    {
        return $this->menuUrl($request).'/order/'.$order->token;
    }

    /** Menu address on the host the guest is on (the session and cart live there). */
    private function menuUrl(Request $request): string
    {
        $restaurant = $this->tenant->get();

        return $request->route('restaurant') !== null ? url('/'.config('tenancy.path_prefix').'/'.$restaurant->slug) : rtrim(url('/'), '/');
    }
}
