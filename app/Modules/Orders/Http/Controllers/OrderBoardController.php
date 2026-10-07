<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\OrderSettings;
use App\Modules\Orders\Support\OrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Live order board for kitchen, waiters and cashiers, and the detail, payment and print screens. */
class OrderBoardController extends Controller
{
    /** How long finished orders stay visible on the board. */
    private const RECENT_HOURS = 3;

    public function __construct(private readonly OrderService $orders, private readonly OrderSettings $settings) {}

    public function index(Request $request): View
    {
        $restaurant = $request->user()->restaurant;

        return view('orders::board.index', [
            'restaurant' => $restaurant,
            'accepting' => $this->settings->accepting($restaurant),
            'canManage' => $request->user()->can('orders.manage'),
            'config' => $this->feed($request->user())->getData(true) + [
                'feedUrl' => route('orders.feed'),
                'statusUrl' => url('/orders'),
                'csrf' => csrf_token(),
                'canPay' => $request->user()->canAny(['orders.manage', 'payments.manage']),
                'canCancel' => $request->user()->can('orders.manage'),
                'interval' => 4000,
            ],
        ]);
    }

    /** Orders the board shows, as JSON. Polled every few seconds. */
    public function feed(?User $user = null): JsonResponse
    {
        $user ??= request()->user();
        $restaurant = $user->restaurant;

        $orders = Order::with('items')
            ->where(fn ($q) => $q->whereIn('status', OrderStatus::OPEN)->orWhere('updated_at', '>=', now()->subHours(self::RECENT_HOURS)))
            ->orderBy('id')->limit(300)->get();

        return response()->json([
            'orders' => $orders->map(fn (Order $o) => $this->present($o, $user))->all(),
            'open' => $orders->filter->isOpen()->count(),
            'accepting' => $this->settings->accepting($restaurant),
            'now' => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, Order $order): View
    {
        return view('orders::board.show', [
            'order' => $order->load(['items', 'events.user']),
            'restaurant' => $request->user()->restaurant,
            'allowed' => $this->allowed($request->user(), $order),
            'canPay' => $request->user()->canAny(['orders.manage', 'payments.manage']) && ! $order->isPaid() && $order->status !== OrderStatus::CANCELLED,
        ]);
    }

    public function transition(Request $request, Order $order): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([...OrderStatus::FLOW, OrderStatus::CANCELLED])],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        abort_unless(in_array($data['status'], $this->allowed($request->user(), $order), true), 403);

        try {
            $this->orders->transition($order, $data['status'], $request->user(), $data['reason'] ?? null);
        } catch (OrderException $e) {
            return $this->failed($request, $e);
        }

        return $this->done($request, $order);
    }

    public function pay(Request $request, Order $order): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->canAny(['orders.manage', 'payments.manage']), 403);
        $data = $request->validate(['method' => ['required', Rule::in(['cash', 'card'])]]);

        try {
            $this->orders->markPaid($order, $data['method'], $request->user());
        } catch (OrderException $e) {
            return $this->failed($request, $e);
        }

        return $this->done($request, $order);
    }

    /** Pause or resume online ordering ("kitchen is overloaded"). */
    public function pause(Request $request): JsonResponse|RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        $settings = $this->settings->for($restaurant);
        $this->settings->save($restaurant, array_merge($settings, ['enabled' => ! $settings['enabled']]));

        return $request->expectsJson() ? response()->json(['accepting' => $this->settings->accepting($restaurant->refresh())]) : back();
    }

    /** Receipt-style ticket for a kitchen or thermal printer. */
    public function ticket(Request $request, Order $order): View
    {
        return view('orders::board.ticket', ['order' => $order->load('items'), 'restaurant' => $request->user()->restaurant]);
    }

    /** @return list<string> statuses this user may set on this order */
    private function allowed(User $user, Order $order): array
    {
        $next = OrderStatus::next($order->status);

        if ($user->can('orders.manage')) {
            return $next;
        }

        // Kitchen staff move food through preparing and ready, nothing else.
        return $user->can('kitchen.view') ? array_values(array_intersect($next, OrderStatus::KITCHEN_STEPS)) : [];
    }

    /** @return array<string, mixed> */
    private function present(Order $o, User $user): array
    {
        $money = fn (int $c) => $o->restaurant->money($c / 100);

        return [
            'id' => $o->id, 'number' => $o->number, 'status' => $o->status, 'type' => $o->type, 'source' => $o->source,
            'table' => $o->table_name, 'name' => $o->customer_name, 'phone' => $o->customer_phone, 'address' => $o->delivery_address, 'note' => $o->note,
            'created' => $o->created_at->toIso8601String(), 'prep_minutes' => $o->prep_minutes,
            'paid' => $o->isPaid(), 'method' => $o->payment_method, 'cancel_reason' => $o->cancel_reason,
            'total' => $money($o->total_cents),
            'items' => $o->items->map(fn ($i) => ['qty' => $i->qty, 'name' => $i->name, 'options' => $i->optionsLabel(), 'note' => $i->note])->all(),
            'allowed' => $this->allowed($user, $o),
            'forward' => OrderStatus::forward($o->status),
        ];
    }

    private function done(Request $request, Order $order): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['ok' => true, 'status' => $order->refresh()->status]) : back()->with('status', __('orders.updated'));
    }

    private function failed(Request $request, OrderException $e): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['error' => $e->reason, 'message' => __('orders.error_'.$e->reason)], 422)
            : back()->withErrors(['order' => __('orders.error_'.$e->reason)]);
    }
}
