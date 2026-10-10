<?php

namespace App\Modules\Reservations\Services;

use App\Modules\Billing\Contracts\RefundableGateway;
use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Payments\PaymentNotification;
use App\Modules\Billing\Support\Money;
use App\Modules\Orders\Services\RestaurantGateways;
use App\Modules\Reservations\Events\ReservationStatusChanged;
use App\Modules\Reservations\Models\Reservation;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * A deposit that holds a table: the guest pays through the restaurant's own gateway account right after booking.
 *
 *  - Until it is paid the booking is "awaiting" and holds the table for deposit_hold_minutes, then it is cancelled.
 *  - Paid: the booking is confirmed (or goes to staff, with "manual confirmation" on).
 *  - Cancelled early enough, or by the restaurant: paid back through the gateway when it supports refunds (Stripe), otherwise "refund due" for staff to pay back in the gateway's dashboard.
 *  - Cancelled late or no-show: the restaurant keeps it. Seated: it counts towards the bill (staff see the amount).
 * References start with "D" (orders use "R"), which is how the shared payment webhook tells them apart.
 */
class ReservationDeposits
{
    public function __construct(private readonly RestaurantGateways $gateways, private readonly ReservationSettings $settings) {}

    public static function isDepositReference(string $reference): bool
    {
        return str_starts_with($reference, 'D');
    }

    /**
     * Sends the guest to the gateway's hosted page.
     *
     * @param  callable(string): array{0: string, 1: string, 2: string}  $urls  reference => [return, cancel, webhook]
     * @return string the page to redirect to
     *
     * @throws InvalidArgumentException payment_unavailable | payment_failed
     */
    public function start(Restaurant $restaurant, Reservation $res, string $code, callable $urls): string
    {
        $gateway = $this->gateways->availableFor($restaurant)[$code] ?? null;

        if (! $gateway || $res->deposit_cents < 1 || ! in_array($res->status, ['awaiting'], true)) {
            throw new InvalidArgumentException('payment_unavailable');
        }

        $reference = 'D'.$restaurant->id.'R'.$res->id;
        $res->forceFill(['deposit_gateway' => $code, 'deposit_reference' => $reference, 'deposit_status' => 'pending'])->save();
        [$return, $cancel, $webhook] = $urls($reference);

        try {
            $result = $gateway->checkout($this->invoice($restaurant, $res), $this->gateways->config($restaurant, $code), $return, $cancel, $webhook);
        } catch (GatewayException $e) {
            Log::warning('Reservation deposit checkout failed', ['reservation' => $res->id, 'gateway' => $code, 'reason' => $e->getMessage()]);

            throw new InvalidArgumentException('payment_failed');
        }

        if (! $result->redirectUrl) {
            throw new InvalidArgumentException('payment_failed');
        }

        $res->forceFill(['deposit_transaction' => mb_substr($result->reference, 0, 120)])->save();

        return $result->redirectUrl;
    }

    /** The throw-away invoice the gateway needs. */
    public function invoice(Restaurant $restaurant, Reservation $res): Invoice
    {
        $amount = $res->deposit_cents / 100;

        return new Invoice([
            'id' => $res->id, 'number' => (string) $res->deposit_reference, 'restaurant_id' => $restaurant->id, 'currency_code' => $restaurant->currency_code, 'total' => $amount,
            'items' => [['description' => $restaurant->name.' · '.__('reservations.deposit_for', ['party' => $res->party_size]), 'quantity' => 1, 'amount' => $amount]],
            'billing' => ['buyer' => ['name' => $res->name, 'email' => $res->email, 'address' => null]],
        ]);
    }

    /** A verified gateway notification (webhook, or the check when the guest returns). True when this call turned the deposit paid. */
    public function finalize(string $reference, PaymentNotification $n): bool
    {
        $res = Reservation::where('deposit_reference', $reference)->where('deposit_status', 'pending')->first();

        if (! $res) {
            return false;
        }

        if ($n->status !== PaymentNotification::SUCCEEDED) {
            $res->forceFill(['deposit_status' => 'failed'])->save();

            return false;
        }

        $restaurant = Restaurant::find($res->restaurant_id);
        $currency = (string) $restaurant?->currency_code;
        $expected = Money::toMinor($res->deposit_cents / 100, $currency);

        // Never trust a "paid" for a different amount or currency than we asked for.
        if (($n->amount > 0 && $n->amount !== $expected) || ($n->currency !== '' && strtoupper($n->currency) !== strtoupper($currency))) {
            Log::warning('Reservation deposit amount mismatch', ['reference' => $reference, 'expected' => $expected, 'got' => $n->amount, 'currency' => $n->currency]);

            return false;
        }

        $res->forceFill(['deposit_status' => 'paid', 'deposit_paid_at' => now(), 'deposit_transaction' => mb_substr($n->transactionId ?: (string) $res->deposit_transaction, 0, 120)])->save();

        // The table was held while the guest paid. If the hold ran out and the slot was lost, the deposit is paid back instead.
        if ($res->status !== 'awaiting') {
            $this->refund($restaurant, $res);

            return true;
        }

        $auto = (bool) $this->settings->for($restaurant)['auto_confirm'];
        $res->forceFill(['status' => $auto ? 'confirmed' : 'pending'])->save();

        if ($auto) {
            $res->update(['table_id' => app(Availability::class)->pickTable($restaurant, $res)?->id]);
        }

        app(ReservationService::class)->mail($restaurant, $res, $auto ? 'reservation_confirmed' : 'reservation_received');
        event(new ReservationStatusChanged($res, 'awaiting', $res->status));

        return true;
    }

    /** What happens to a paid deposit when the booking ends. $by is 'guest' or 'staff'. */
    public function settle(Restaurant $restaurant, Reservation $res, string $to, string $by): void
    {
        if ($res->deposit_status !== 'paid') {
            return;
        }

        match ($to) {
            'seated', 'completed' => $res->forceFill(['deposit_status' => 'applied'])->save(),
            'no_show' => $res->forceFill(['deposit_status' => 'forfeited'])->save(),
            'cancelled' => ($by === 'staff' || $this->early($restaurant, $res)) ? $this->refund($restaurant, $res) : $res->forceFill(['deposit_status' => 'forfeited'])->save(),
            default => null,
        };
    }

    /** Pays the deposit back through the gateway when it can, otherwise flags it for staff. */
    public function refund(Restaurant $restaurant, Reservation $res): void
    {
        $gateway = $res->deposit_gateway ? $this->gateways->find($res->deposit_gateway) : null;

        if ($gateway instanceof RefundableGateway && $res->deposit_transaction) {
            try {
                $gateway->refund($res->deposit_transaction, Money::toMinor($res->deposit_cents / 100, (string) $restaurant->currency_code), (string) $restaurant->currency_code, $this->gateways->config($restaurant, $res->deposit_gateway));
                $res->forceFill(['deposit_status' => 'refunded'])->save();

                return;
            } catch (Throwable $e) {
                Log::warning('Reservation deposit refund failed', ['reservation' => $res->id, 'reason' => $e->getMessage()]);
            }
        }

        $res->forceFill(['deposit_status' => 'refund_due'])->save();
    }

    /** Staff paid a "refund due" deposit back by hand. */
    public function markRefunded(Reservation $res): void
    {
        if ($res->deposit_status === 'refund_due') {
            $res->forceFill(['deposit_status' => 'refunded'])->save();
        }
    }

    /** Cancels bookings whose deposit was not paid in time, freeing their tables. @return int how many */
    public function expire(Restaurant $restaurant): int
    {
        $cutoff = now()->subMinutes(max(5, (int) $this->settings->for($restaurant)['deposit_hold_minutes']));
        $count = 0;

        foreach (Reservation::where('status', 'awaiting')->where('created_at', '<', $cutoff)->limit(200)->get() as $res) {
            $res->forceFill(['status' => 'cancelled', 'deposit_status' => 'failed'])->save();
            event(new ReservationStatusChanged($res, 'awaiting', 'cancelled'));
            $count++;
        }

        return $count;
    }

    private function early(Restaurant $restaurant, Reservation $res): bool
    {
        return $res->starts_at->gte(now()->addHours((int) $this->settings->for($restaurant)['deposit_refund_hours']));
    }
}
