<?php

namespace App\Modules\Orders\Services;

use App\Models\User;
use App\Modules\Billing\Contracts\RefundableGateway;
use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Support\Money;
use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderEvent;
use App\Modules\Orders\Models\OrderPayment;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tables\Models\DiningTable;
use Illuminate\Support\Facades\DB;

/**
 * Who paid what towards an order: cash and card on the spot, online checkouts, split bills, partial payments, tips and refunds.
 * Money values are cents of the order currency. An order is "paid" (paid_at set) once the payments cover its total.
 */
class PaymentLedger
{
    public function __construct(private readonly RestaurantGateways $gateways) {}

    public function remaining(Order $order): int
    {
        return max(0, $order->total_cents - $order->paid_cents);
    }

    /**
     * Take a payment on the spot (or record an online one that already succeeded).
     *
     * @param  int|null  $amount  cents towards the bill; null = everything still owed
     *
     * @throws OrderException cannot_pay | payment_unavailable | invalid_amount
     */
    public function record(Order $order, string $method, ?int $amount = null, int $tip = 0, ?User $by = null, array $extra = []): OrderPayment
    {
        if (! in_array($method, ['cash', 'card', 'online'], true)) {
            throw new OrderException('payment_unavailable');
        }

        return DB::transaction(function () use ($order, $method, $amount, $tip, $by, $extra) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $remaining = $this->remaining($order);

            if ($order->status === OrderStatus::CANCELLED || $remaining <= 0) {
                throw new OrderException('cannot_pay');
            }

            $amount ??= $remaining;

            if ($amount < 1) {
                throw new OrderException('invalid_amount');
            }

            $amount = min($amount, $remaining);
            $payment = OrderPayment::create([
                'order_id' => $order->id, 'method' => $method, 'amount_cents' => $amount, 'tip_cents' => max(0, $tip),
                'status' => OrderPayment::PAID, 'paid_at' => now(), 'user_id' => $by?->id,
                'commission_cents' => $method === 'online' ? $this->commission($amount) : 0,
            ] + $extra);

            $this->apply($order, $payment, $by);

            return $payment;
        });
    }

    /** Adds a successful payment to its order and closes the bill when it is covered. */
    public function apply(Order $order, OrderPayment $payment, ?User $by = null): void
    {
        $order->forceFill(['paid_cents' => $order->paid_cents + $payment->amount_cents, 'tip_cents' => $order->tip_cents + $payment->tip_cents, 'payment_method' => $payment->method]);
        $closed = $order->paid_cents >= $order->total_cents && $order->paid_at === null;

        if ($closed) {
            $order->paid_at = now();
        }

        $order->save();
        OrderEvent::create(['order_id' => $order->id, 'user_id' => $by?->id, 'type' => 'payment', 'note' => $payment->method]);

        if ($closed) {
            DB::afterCommit(fn () => event(new OrderPaid($order)));
        }
    }

    /**
     * Everything still owed at a table, paid in one go (the guests asked for the bill). The tip goes on the first order.
     *
     * @return int number of orders settled
     */
    public function closeTable(DiningTable $table, string $method, int $tip = 0, ?User $by = null): int
    {
        $orders = Order::where('table_id', $table->id)->whereNull('paid_at')->where('status', '!=', OrderStatus::CANCELLED)->where('created_at', '>=', now()->subHours(12))->orderBy('id')->get();
        $settled = 0;

        foreach ($orders as $order) {
            if ($this->remaining($order) > 0) {
                $this->record($order, $method, null, $settled === 0 ? $tip : 0, $by);
                $settled++;
            }
        }

        return $settled;
    }

    /** Platform commission on an online payment, in cents. */
    public function commission(int $amountCents): int
    {
        return (int) round($amountCents * $this->gateways->commissionPercent() / 100);
    }

    /**
     * Give money back. Online payments go through the gateway when it supports refunds; otherwise (and for cash)
     * this records a refund that is then paid out by hand.
     *
     * @return array{refunded: int, via_gateway: bool}
     *
     * @throws OrderException invalid_amount | refund_failed
     */
    public function refund(OrderPayment $payment, ?int $amountCents, ?User $by = null, ?string $reason = null): array
    {
        $order = $payment->order;
        $max = $payment->refundable();
        $amount = min($amountCents ?? $max, $max);

        if ($amount < 1) {
            throw new OrderException('invalid_amount');
        }

        $viaGateway = false;

        if ($payment->method === 'online' && $payment->gateway && $payment->transaction_id && ($gateway = $this->gateways->find($payment->gateway)) instanceof RefundableGateway) {
            try {
                $gateway->refund($payment->transaction_id, Money::toMinor($amount / 100, (string) $order->currency_code), (string) $order->currency_code, $this->gateways->config($order->restaurant, $payment->gateway));
                $viaGateway = true;
            } catch (GatewayException) {
                throw new OrderException('refund_failed');
            }
        }

        DB::transaction(function () use ($payment, $order, $amount, $by, $reason) {
            $payment->forceFill(['refunded_cents' => $payment->refunded_cents + $amount, 'note' => $reason ? mb_substr(trim(strip_tags($reason)), 0, 200) : $payment->note])->save();
            // The platform keeps no commission on money that went back.
            if ($payment->commission_cents > 0 && $payment->commission_billed_at === null) {
                $payment->forceFill(['commission_cents' => $this->commission(max(0, $payment->amount_cents - $payment->refunded_cents))])->save();
            }

            $order->forceFill(['paid_cents' => max(0, $order->paid_cents - $amount), 'refunded_cents' => $order->refunded_cents + $amount]);

            if ($order->paid_cents < $order->total_cents) {
                $order->paid_at = null; // owes money again
            }

            $order->save();
            OrderEvent::create(['order_id' => $order->id, 'user_id' => $by?->id, 'type' => 'refund', 'note' => $amount.' '.$payment->method.($reason ? ' · '.mb_substr($reason, 0, 120) : '')]);
        });

        return ['refunded' => $amount, 'via_gateway' => $viaGateway];
    }
}
