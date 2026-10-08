<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\DeliveryZone;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\PaymentLedger;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Orders\Support\OrderType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** Delivery: the areas and their fees (owner), and the courier's own list of drops (courier). */
class DeliveryController extends Controller
{
    public function __construct(private readonly OrderService $orders, private readonly PaymentLedger $ledger) {}

    // ---- zones ---------------------------------------------------------------------------

    public function zones(): View
    {
        return view('orders::delivery.zones', ['zones' => DeliveryZone::orderBy('sort')->orderBy('id')->get()]);
    }

    public function storeZone(Request $request): RedirectResponse
    {
        DeliveryZone::create($this->zoneData($request) + ['sort' => (int) DeliveryZone::max('sort') + 1]);

        return back()->with('status', __('orders.zone_saved'));
    }

    public function updateZone(Request $request, DeliveryZone $zone): RedirectResponse
    {
        $zone->update($this->zoneData($request) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('status', __('orders.zone_saved'));
    }

    public function destroyZone(DeliveryZone $zone): RedirectResponse
    {
        $zone->delete();

        return back()->with('status', __('orders.zone_deleted'));
    }

    /** @return array<string, mixed> */
    private function zoneData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'], 'fee' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'min_order' => ['nullable', 'numeric', 'min:0', 'max:99999'], 'eta_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
        ]);

        return ['name' => trim($data['name']), 'fee' => $data['fee'] ?? 0, 'min_order' => $data['min_order'] ?? 0, 'eta_minutes' => $data['eta_minutes'] ?? null];
    }

    // ---- managers hand a delivery to a courier --------------------------------------------

    public function assign(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()->canAny(['orders.manage', 'delivery.manage']), 403);
        abort_unless($order->type === OrderType::DELIVERY, 404);
        $courierId = $request->validate(['courier_id' => ['nullable', 'integer']])['courier_id'] ?? null;

        if ($courierId !== null && ! $this->couriers($request)->contains('id', $courierId)) {
            return back()->withErrors(['order' => __('orders.error_courier_invalid')]);
        }

        $order->forceFill(['courier_id' => $courierId])->save();

        return back()->with('status', __('admin.saved'));
    }

    /** @return Collection<int, User> staff who can deliver */
    public function couriers(Request $request)
    {
        return $request->user()->restaurant->users()->whereNull('disabled_at')->get()->filter(fn (User $u) => $u->can('delivery.view'))->values();
    }

    // ---- the courier's own page -----------------------------------------------------------

    public function mine(Request $request): View
    {
        $user = $request->user();
        $base = Order::with('items')->where('type', OrderType::DELIVERY);

        return view('orders::delivery.courier', [
            'mine' => (clone $base)->where('courier_id', $user->id)->where('status', OrderStatus::READY)->orderBy('id')->get(),
            'preparing' => (clone $base)->where('courier_id', $user->id)->whereIn('status', [OrderStatus::NEW, OrderStatus::ACCEPTED, OrderStatus::PREPARING])->orderBy('id')->get(),
            'open' => (clone $base)->whereNull('courier_id')->where('status', OrderStatus::READY)->orderBy('id')->get(),
            'restaurant' => $user->restaurant,
        ]);
    }

    public function claim(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->type === OrderType::DELIVERY && $order->courier_id === null, 404);
        $order->forceFill(['courier_id' => $request->user()->id])->save();

        return back();
    }

    public function leave(Request $request, Order $order): RedirectResponse
    {
        $order = $this->mineOrFail($request, $order);

        try {
            $this->orders->dispatch($order, $request->user());
        } catch (OrderException $e) {
            return back()->withErrors(['order' => __('orders.error_'.$e->reason)]);
        }

        return back();
    }

    /** Handed over. A courier who collects the money records it here (cash or card), so the order closes paid. */
    public function delivered(Request $request, Order $order): RedirectResponse
    {
        $order = $this->mineOrFail($request, $order);
        $data = $request->validate(['collected' => ['nullable', 'in:cash,card']]);

        try {
            if (! empty($data['collected']) && $this->ledger->remaining($order) > 0) {
                $this->ledger->record($order, $data['collected'], null, 0, $request->user());
            }

            $this->orders->transition($order, OrderStatus::COMPLETED, $request->user());
        } catch (OrderException $e) {
            return back()->withErrors(['order' => __('orders.error_'.$e->reason)]);
        }

        return back()->with('status', __('orders.delivered_done'));
    }

    private function mineOrFail(Request $request, Order $order): Order
    {
        abort_unless($order->type === OrderType::DELIVERY && $order->courier_id === $request->user()->id, 404);

        return $order;
    }
}
