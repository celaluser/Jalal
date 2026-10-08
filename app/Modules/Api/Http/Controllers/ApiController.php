<?php

namespace App\Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Services\ApiResources;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Reservations\Models\Reservation;
use App\Modules\Reservations\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use App\Modules\Orders\Support\OrderType;
use Illuminate\Http\Request;

/** REST API v1: the menu and the orders of the token's restaurant. */
class ApiController extends Controller
{
    public function __construct(private readonly ApiResources $resources) {}

    public function me(Request $request): JsonResponse
    {
        $r = $request->attributes->get('api_restaurant');

        return response()->json(['restaurant' => ['id' => $r->id, 'name' => $r->name, 'currency' => $r->currency_code, 'locale' => $r->locale], 'abilities' => $request->attributes->get('api_token')->abilities]);
    }

    public function menu(): JsonResponse
    {
        return response()->json(['categories' => Category::orderBy('sort')->get()->map(fn ($c) => $this->resources->category($c))->all(), 'products' => Product::orderBy('sort')->get()->map(fn ($p) => $this->resources->product($p))->all()]);
    }

    public function updateProduct(Request $request, int $product): JsonResponse
    {
        $model = Product::findOrFail($product);
        $data = $request->validate(['price' => ['sometimes', 'numeric', 'min:0', 'max:9999999'], 'is_available' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean'], 'stock_qty' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000']]);
        $model->update($data);

        return response()->json(['product' => $this->resources->product($model->fresh())]);
    }

    public function orders(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'in:'.implode(',', [...OrderStatus::FLOW, OrderStatus::CANCELLED])], 'since' => ['nullable', 'date'], 'per_page' => ['nullable', 'integer', 'between:1,100']]);
        $page = Order::with('items')->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('since'), fn ($q, $d) => $q->where('created_at', '>=', $d))->orderByDesc('id')->paginate((int) $request->query('per_page', 25));

        return response()->json(['data' => $page->getCollection()->map(fn ($o) => $this->resources->order($o))->all(), 'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function order(int $order): JsonResponse
    {
        return response()->json(['order' => $this->resources->order(Order::with('items')->findOrFail($order))]);
    }

    public function orderStatus(Request $request, OrderService $orders, int $order): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', [...OrderStatus::FLOW, OrderStatus::CANCELLED])], 'reason' => ['nullable', 'string', 'max:200']]);
        $model = Order::findOrFail($order);

        try {
            $orders->transition($model, $data['status'], null, $data['reason'] ?? null);
        } catch (OrderException $e) {
            return response()->json(['message' => 'This order cannot move to that status.', 'code' => $e->reason, 'status' => $model->status], 422);
        }

        return response()->json(['order' => $this->resources->order($model->fresh('items'))]);
    }

    /** Places an order exactly as the guest menu would (same rules, prices and stock checks). Send an Idempotency-Key header to make retries safe. */
    public function createOrder(Request $request, OrderService $orders): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', OrderType::ALL)], 'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_id' => ['required', 'integer'], 'lines.*.qty' => ['nullable', 'integer', 'between:1,50'], 'lines.*.options' => ['nullable', 'array'], 'lines.*.variant_id' => ['nullable', 'integer'], 'lines.*.note' => ['nullable', 'string', 'max:200'],
            'table_id' => ['nullable', 'integer'], 'customer_name' => ['nullable', 'string', 'max:80'], 'customer_phone' => ['nullable', 'string', 'max:40'], 'customer_email' => ['nullable', 'email', 'max:190'],
            'delivery_address' => ['nullable', 'string', 'max:300'], 'note' => ['nullable', 'string', 'max:300'], 'promo_code' => ['nullable', 'string', 'max:40'], 'payment_method' => ['nullable', 'string', 'max:20'],
        ]);
        $data['idempotency_key'] = $request->header('Idempotency-Key');

        try {
            $order = $orders->place($request->attributes->get('api_restaurant'), $data, 'api');
        } catch (OrderException $e) {
            return response()->json(['message' => 'The order could not be placed.', 'code' => $e->reason], 422);
        }

        return response()->json(['order' => $this->resources->order($order->load('items'))], 201);
    }

    public function reservations(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'in:pending,confirmed,seated,completed,cancelled,no_show'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $page = Reservation::when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->when($request->query('from'), fn ($q, $d) => $q->where('starts_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->where('starts_at', '<=', $d))->orderBy('starts_at')->paginate(50);

        return response()->json(['data' => $page->getCollection()->map(fn ($r) => $this->resources->reservation($r))->all(), 'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function createReservation(Request $request, ReservationService $service): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'], 'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:190'], 'party_size' => ['required', 'integer', 'between:1,100'],
            'date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i'], 'note' => ['nullable', 'string', 'max:300'],
        ]);

        try {
            $reservation = $service->book($request->attributes->get('api_restaurant'), $data, 'api');
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => 'The reservation could not be made.', 'code' => $e->getMessage()], 422);
        }

        return response()->json(['reservation' => $this->resources->reservation($reservation)], 201);
    }

    public function reservationStatus(Request $request, ReservationService $service, int $reservation): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:confirmed,seated,completed,cancelled,no_show']]);
        $model = Reservation::findOrFail($reservation);

        try {
            $service->setStatus($request->attributes->get('api_restaurant'), $model, $data['status']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => 'This reservation cannot move to that status.', 'code' => $e->getMessage(), 'status' => $model->status], 422);
        }

        return response()->json(['reservation' => $this->resources->reservation($model->fresh())]);
    }

    public function customers(Request $request): JsonResponse
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'between:1,100'], 'q' => ['nullable', 'string', 'max:80']]);
        $page = Customer::when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($t, '%_\\').'%')->orWhere('email', 'like', '%'.addcslashes($t, '%_\\').'%')))
            ->orderByDesc('id')->paginate((int) $request->query('per_page', 25));

        return response()->json(['data' => $page->getCollection()->map(fn ($c) => $this->resources->customer($c))->all(), 'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    /** The machine-readable description of this API (OpenAPI 3). Public, no token needed. */
    public function openapi(): JsonResponse
    {
        return response()->json(json_decode((string) file_get_contents(base_path('docs/openapi.json')), true));
    }
}
