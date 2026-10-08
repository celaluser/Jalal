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
use Illuminate\Http\JsonResponse;
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
}
