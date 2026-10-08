<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderPayment;
use App\Modules\Orders\Services\OnlinePayments;
use App\Modules\Orders\Services\PaymentLedger;
use App\Modules\Orders\Services\RestaurantGateways;
use App\Modules\Orders\Support\OrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** The guest pays from their phone: the whole bill, an equal share, any amount, or the whole table, with an optional tip. */
class OrderPaymentController extends Controller
{
    public const TIP_PRESETS = [0, 5, 10, 15, 20];

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly RestaurantGateways $gateways,
        private readonly PaymentLedger $ledger,
        private readonly OnlinePayments $online,
    ) {}

    public function start(Request $request): JsonResponse
    {
        $restaurant = $this->tenant->get();
        $order = $this->order($request);

        $data = $request->validate([
            'gateway' => ['required', 'string', 'max:24'],
            'mode' => ['required', 'in:full,split,custom,tab'],
            'people' => ['nullable', 'integer', 'min:2', 'max:20'],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:9999999'],
            'tip_percent' => ['nullable', 'integer', 'in:'.implode(',', self::TIP_PRESETS)],
            'tip' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);

        try {
            if ($order->status === OrderStatus::CANCELLED || $this->ledger->remaining($order) < 1) {
                throw new OrderException('cannot_pay');
            }

            [$allocations, $base] = $this->allocate($order, $data);
            $tip = isset($data['tip']) ? (int) round((float) $data['tip'] * 100) : (int) round($base * (int) ($data['tip_percent'] ?? 0) / 100);
            $tip = min($tip, max($base, 100000)); // a tip larger than the bill itself is a typo

            $base_url = $request->route('restaurant') !== null ? url('/'.config('tenancy.path_prefix').'/'.$request->route('restaurant')) : url('/');
            $url = $this->online->start($restaurant, $order, $allocations, $tip, $data['gateway'], fn (string $ref) => [
                $base_url.'/order/'.$order->token.'/pay/return?ref='.urlencode($ref),
                $base_url.'/order/'.$order->token,
                $base_url.'/pay/'.$data['gateway'].'/webhook',
            ]);
        } catch (OrderException $e) {
            return response()->json(['error' => $e->reason, 'message' => __('orders.error_'.$e->reason)], 422);
        }

        return response()->json(['url' => $url]);
    }

    /** The guest comes back from the hosted page. Whatever the URL says, the gateway is asked whether it was paid. */
    public function return(Request $request): RedirectResponse
    {
        $restaurant = $this->tenant->get();
        $order = $this->order($request);
        $reference = (string) $request->query('ref');
        $row = OrderPayment::where('reference', $reference)->where('order_id', $order->id)->first();
        $back = ($request->route('restaurant') !== null ? url('/'.config('tenancy.path_prefix').'/'.$request->route('restaurant')) : url('/')).'/order/'.$order->token;

        if (! $row || ! $row->gateway) {
            return redirect($back);
        }

        $gateway = $this->gateways->find($row->gateway);

        try {
            if ($row->status === OrderPayment::PENDING && $gateway) {
                $total = OrderPayment::where('reference', $reference)->selectRaw('sum(amount_cents + tip_cents) as t')->value('t');
                $notification = $gateway->confirmReturn($request, $this->online->invoice($restaurant, $order, $reference, (int) $total, $row->id), $this->gateways->config($restaurant, $row->gateway));

                if ($notification !== null && $notification->invoiceNumber === $reference) {
                    $this->online->finalize($reference, $notification);
                }
            }
        } catch (GatewayException $e) {
            Log::warning('Guest payment return could not be confirmed', ['gateway' => $row->gateway, 'reference' => $reference, 'reason' => $e->getMessage()]);
        }

        $paid = OrderPayment::where('reference', $reference)->where('status', OrderPayment::PAID)->exists();

        return redirect($back)->with('pay_result', $paid ? 'paid' : 'pending');
    }

    /** Server-to-server callback of the restaurant's gateway account. Authenticity is the gateway's job (signature or re-fetch). */
    public function webhook(Request $request): JsonResponse
    {
        $restaurant = $this->tenant->get();
        $code = (string) $request->route('gateway');
        $gateway = $this->gateways->find($code);
        abort_if($gateway === null || ! $this->gateways->ready($restaurant, $code), 404);

        try {
            $notification = $gateway->webhook($request, $this->gateways->config($restaurant, $code));
        } catch (GatewayException $e) {
            Log::warning('Rejected guest payment webhook', ['gateway' => $code, 'reason' => $e->getMessage()]);

            return response()->json(['ok' => false], 400);
        }

        if ($notification !== null) {
            $this->online->finalize($notification->invoiceNumber, $notification);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * What is being paid: [order id => cents], and the total of those cents.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<int, int>, 1: int}
     *
     * @throws OrderException invalid_amount
     */
    private function allocate(Order $order, array $data): array
    {
        $remaining = $this->ledger->remaining($order);

        if ($data['mode'] === 'tab') {
            $orders = $order->tab_id
                ? Order::where('tab_id', $order->tab_id)->whereNull('paid_at')->where('status', '!=', OrderStatus::CANCELLED)->orderBy('id')->get()
                : collect([$order]);
            $alloc = $orders->mapWithKeys(fn (Order $o) => [$o->id => $this->ledger->remaining($o)])->all();

            return [$alloc, array_sum($alloc)];
        }

        $cents = match ($data['mode']) {
            'split' => (int) ceil($remaining / max(2, (int) ($data['people'] ?? 2))),
            'custom' => (int) round((float) ($data['amount'] ?? 0) * 100),
            default => $remaining,
        };

        if ($cents < 1) {
            throw new OrderException('invalid_amount');
        }

        $cents = min($cents, $remaining);

        return [[$order->id => $cents], $cents];
    }

    private function order(Request $request): Order
    {
        return Order::where('token', (string) $request->route('token'))->firstOrFail();
    }
}
