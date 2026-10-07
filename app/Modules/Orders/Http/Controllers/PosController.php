<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Menu\Services\MenuService;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Support\OrderType;
use App\Modules\Tables\Models\DiningTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Order entry for staff: a waiter or cashier keys in an order for a table, a walk-in or a phone call. */
class PosController extends Controller
{
    public function __construct(private readonly OrderService $orders, private readonly MenuService $menu) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $restaurant = $user->restaurant;

        return view('orders::pos.index', ['config' => [
            'menu' => $this->menu->tree($restaurant, app()->getLocale()),
            'tables' => DiningTable::where('is_active', true)->orderBy('name')->get()->map(fn ($t) => ['id' => $t->id, 'name' => table_label($t->name)])->all(),
            'currency' => $restaurant->currency_code,
            'locale' => str_replace('_', '-', app()->getLocale()),
            'canPay' => $user->canAny(['orders.manage', 'payments.manage']),
            'storeUrl' => route('orders.pos.store'),
            'boardUrl' => route('orders.board'),
            'csrf' => csrf_token(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(OrderType::ALL)],
            'table_id' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:80'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'customer_email' => ['nullable', 'string', 'max:190'],
            'promo_code' => ['nullable', 'string', 'max:40'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:300'],
            'payment_method' => ['nullable', Rule::in(['cash', 'card'])],
            'paid' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'lines' => ['required', 'array', 'min:1', 'max:60'],
        ]);

        $user = $request->user();
        $restaurant = $user->restaurant;
        $data['payment_method'] ??= 'cash';

        try {
            $order = $this->orders->place($restaurant, $data, 'staff', $user);

            // Taking money is a cashier/owner decision; a waiter's "paid" flag is ignored.
            if ($request->boolean('paid') && $user->canAny(['orders.manage', 'payments.manage']) && ! $order->isPaid()) {
                $this->orders->markPaid($order, $data['payment_method'], $user);
            }
        } catch (OrderException $e) {
            return response()->json(['error' => $e->reason, 'message' => __('orders.error_'.$e->reason)], 422);
        }

        return response()->json(['number' => $order->number, 'url' => route('orders.show', $order->id)], 201);
    }
}
