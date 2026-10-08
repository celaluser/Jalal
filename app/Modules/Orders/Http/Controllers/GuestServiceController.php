<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\ServiceRequest;
use App\Modules\Orders\Services\PushNotifier;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tables\Models\DiningTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** What a guest at the table can do besides ordering: call the waiter, see the table's bill, hear about an order, order the same again. */
class GuestServiceController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** "Call the waiter" / "Bring the bill". Only guests who scanned a table code can ask. */
    public function request(Request $request): JsonResponse
    {
        $table = $this->table($request);
        abort_unless($table, 403);
        $data = $request->validate(['kind' => ['required', Rule::in(ServiceRequest::KINDS)], 'note' => ['nullable', 'string', 'max:120']]);

        // A second tap for the same thing while it is still open is the same request.
        $open = ServiceRequest::where('table_id', $table->id)->where('kind', $data['kind'])->where('status', 'open')->exists();

        if (! $open) {
            ServiceRequest::create(['table_id' => $table->id, 'table_name' => $table->name, 'kind' => $data['kind'], 'note' => isset($data['note']) ? trim(strip_tags($data['note'])) ?: null : null]);
        }

        return response()->json(['ok' => true, 'message' => __('orders.request_sent')]);
    }

    /** The shared bill of this table: everything ordered at it this sitting that is not paid yet. No names, no phone numbers. */
    public function tab(Request $request): JsonResponse
    {
        $restaurant = $this->tenant->get();
        $table = $this->table($request);
        abort_unless($table, 403);

        $orders = Order::with('items')->where('table_id', $table->id)->whereNull('paid_at')->where('status', '!=', OrderStatus::CANCELLED)->where('created_at', '>=', now()->subHours(6))->orderBy('id')->get();

        return response()->json([
            'table' => table_label($table->name),
            'orders' => $orders->map(fn (Order $o) => ['number' => $o->number, 'status' => $o->status, 'items' => $o->items->map(fn ($i) => $i->qty.'× '.$i->name)->all(), 'total' => $restaurant->money($o->total_cents / 100)])->all(),
            'total' => $restaurant->money($orders->sum('total_cents') / 100),
        ])->header('Cache-Control', 'no-store');
    }

    /** Browser push for one order, from the status page. */
    public function subscribePush(Request $request): JsonResponse
    {
        $order = Order::where('token', (string) $request->route('token'))->firstOrFail();
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:500'], 'keys.p256dh' => ['required', 'string', 'max:200'], 'keys.auth' => ['required', 'string', 'max:100'],
        ]);
        abort_unless($order->isOpen(), 422);

        app(PushNotifier::class)->subscribe($order, $data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth']);

        return response()->json(['ok' => true]);
    }

    /** The lines of a past order as the cart wants them, to order the same again. Prices are looked up again when the cart is priced. */
    public function reorder(Request $request): JsonResponse
    {
        $order = Order::with('items')->where('token', (string) $request->route('token'))->firstOrFail();

        $lines = $order->items->filter(fn ($i) => $i->product_id)->map(function ($i) {
            $r = (array) ($i->reorder ?? []);

            return ['product_id' => $i->product_id, 'variant_id' => $r['variant_id'] ?? null, 'combo' => ! empty($r['combo']) ? $r['combo'] : null, 'options' => array_values($r['options'] ?? []), 'qty' => $i->qty, 'note' => $i->note ?? ''];
        })->values()->all();

        return response()->json(['lines' => $lines]);
    }

    private function table(Request $request): ?DiningTable
    {
        $id = $request->session()->get('table.'.$this->tenant->id());

        return $id ? DiningTable::where('is_active', true)->find($id) : null;
    }
}
