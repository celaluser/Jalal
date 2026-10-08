<?php

namespace App\Modules\Orders\Services;

use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Payments\PaymentNotification;
use App\Modules\Billing\Support\Money;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderPayment;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pays an order (or a whole table's bill) through the restaurant's own gateway account.
 *
 * start() writes a pending payment row per order, sends the guest to the gateway's hosted page, and finalize() marks them paid
 * once the gateway confirms (by webhook or on the guest's return). The gateways are the subscription ones, driven with a
 * throw-away Invoice that only carries the reference, amount, currency and buyer.
 */
class OnlinePayments
{
    public function __construct(private readonly RestaurantGateways $gateways, private readonly PaymentLedger $ledger) {}

    /**
     * @param  array<int, int>  $allocations  order id => cents towards that order's bill
     * @param  callable(string): array{0: string, 1: string, 2: string}  $urls  reference => [return, cancel, webhook]
     * @return string the gateway page to send the guest to
     *
     * @throws OrderException payment_unavailable | invalid_amount | payment_failed
     */
    public function start(Restaurant $restaurant, Order $head, array $allocations, int $tipCents, string $code, callable $urls): string
    {
        $gateway = $this->gateways->availableFor($restaurant)[$code] ?? null;

        if (! $gateway) {
            throw new OrderException('payment_unavailable');
        }

        $allocations = array_filter($allocations, fn ($c) => $c > 0);

        if ($allocations === []) {
            throw new OrderException('invalid_amount');
        }

        $rows = DB::transaction(function () use ($allocations, $tipCents, $code) {
            $rows = [];

            foreach ($allocations as $orderId => $cents) {
                $rows[] = OrderPayment::create([
                    'order_id' => $orderId, 'method' => 'online', 'gateway' => $code, 'amount_cents' => $cents,
                    'tip_cents' => $rows === [] ? max(0, $tipCents) : 0, 'status' => OrderPayment::PENDING,
                ]);
            }

            $reference = 'R'.$rows[0]->restaurant_id.'P'.$rows[0]->id;
            OrderPayment::whereIn('id', collect($rows)->pluck('id'))->update(['reference' => $reference]);

            return collect($rows)->each->setAttribute('reference', $reference);
        });

        $reference = (string) $rows[0]->reference;
        $total = (int) $rows->sum('amount_cents') + (int) $rows->sum('tip_cents');
        [$return, $cancel, $webhook] = $urls($reference);

        try {
            $result = $gateway->checkout($this->invoice($restaurant, $head, $reference, $total, $rows[0]->id), $this->gateways->config($restaurant, $code), $return, $cancel, $webhook);
        } catch (GatewayException $e) {
            OrderPayment::where('reference', $reference)->update(['status' => OrderPayment::FAILED, 'note' => mb_substr($e->getMessage(), 0, 200)]);

            throw new OrderException('payment_failed');
        }

        if (! $result->redirectUrl) {
            OrderPayment::where('reference', $reference)->update(['status' => OrderPayment::FAILED]);

            throw new OrderException('payment_failed');
        }

        OrderPayment::where('reference', $reference)->update(['transaction_id' => mb_substr($result->reference, 0, 120)]);

        return $result->redirectUrl;
    }

    /** The throw-away invoice a gateway needs: number, amount, currency, a description and who is paying. */
    public function invoice(Restaurant $restaurant, Order $order, string $reference, int $totalCents, int $id = 0): Invoice
    {
        return new Invoice([
            'id' => $id, 'number' => $reference, 'restaurant_id' => $restaurant->id, 'currency_code' => $restaurant->currency_code, 'total' => $totalCents / 100,
            'items' => [['description' => $restaurant->name.' · #'.$order->number, 'quantity' => 1, 'amount' => $totalCents / 100]],
            'billing' => ['buyer' => ['name' => $order->customer_name ?: $restaurant->name, 'email' => $order->customer_email, 'address' => $order->delivery_address]],
        ]);
    }

    /**
     * Marks the pending rows of a checkout paid (or failed) from a verified gateway notification. Safe to call twice:
     * rows that are no longer pending are left alone. Returns true when this call turned them paid.
     */
    public function finalize(string $reference, PaymentNotification $n): bool
    {
        $rows = OrderPayment::where('reference', $reference)->where('status', OrderPayment::PENDING)->orderBy('id')->get();

        if ($rows->isEmpty()) {
            return false;
        }

        if ($n->status !== PaymentNotification::SUCCEEDED) {
            OrderPayment::whereIn('id', $rows->pluck('id'))->update(['status' => OrderPayment::FAILED]);

            return false;
        }

        $order = Order::find($rows[0]->order_id);
        $currency = (string) $order?->currency_code;
        $expected = Money::toMinor(($rows->sum('amount_cents') + $rows->sum('tip_cents')) / 100, $currency);

        // Never trust a "paid" for a different amount or currency than we asked for.
        if (($n->amount > 0 && $n->amount !== $expected) || ($n->currency !== '' && strtoupper($n->currency) !== strtoupper($currency))) {
            Log::warning('Guest payment amount mismatch', ['reference' => $reference, 'expected' => $expected, 'got' => $n->amount, 'currency' => $n->currency]);

            return false;
        }

        DB::transaction(function () use ($rows, $n) {
            foreach ($rows as $row) {
                $order = Order::whereKey($row->order_id)->lockForUpdate()->first();
                $row->forceFill(['status' => OrderPayment::PAID, 'paid_at' => now(), 'transaction_id' => mb_substr($n->transactionId ?: (string) $row->transaction_id, 0, 120), 'commission_cents' => $this->ledger->commission($row->amount_cents)])->save();

                if ($order) {
                    $this->ledger->apply($order, $row);
                }
            }
        });

        return true;
    }
}
