<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Payments\PaymentNotification;
use App\Modules\Billing\Support\Money;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns a gateway notification into booked money. Rules:
 *  - replays of the same gateway transaction are no-ops (unique gateway+transaction_id);
 *  - the paid amount and currency must equal the invoice total, otherwise nothing is activated;
 *  - only an open invoice is settled, and settling activates the plan exactly once.
 */
class PaymentProcessor
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function handle(PaymentNotification $notification): ?Payment
    {
        return DB::transaction(function () use ($notification) {
            $invoice = Invoice::allTenants()->where('number', $notification->invoiceNumber)->lockForUpdate()->first();

            if (! $invoice) {
                Log::warning('Payment for unknown invoice', ['gateway' => $notification->gateway, 'invoice' => $notification->invoiceNumber]);

                return null;
            }

            $existing = Payment::allTenants()->where('gateway', $notification->gateway)->where('transaction_id', $notification->transactionId)->first();

            if ($existing?->status === 'succeeded') {
                return $existing; // webhook replay or duplicate return
            }

            $status = $notification->status;

            if ($status === PaymentNotification::SUCCEEDED && ! $this->amountMatches($invoice, $notification)) {
                Log::error('Payment amount mismatch', ['invoice' => $invoice->number, 'gateway' => $notification->gateway, 'paid' => $notification->amount, 'currency' => $notification->currency]);
                $status = 'mismatch';
            }

            $payment = Payment::allTenants()->updateOrCreate(
                ['gateway' => $notification->gateway, 'transaction_id' => $notification->transactionId],
                [
                    'restaurant_id' => $invoice->restaurant_id,
                    'invoice_id' => $invoice->id,
                    'amount' => $notification->amount,
                    'currency_code' => $notification->currency,
                    'status' => $status,
                    'payload' => $notification->payload,
                ]
            );

            if ($status === PaymentNotification::SUCCEEDED) {
                if ($invoice->status === 'open') {
                    $this->settle($invoice, $notification->gateway, $notification->transactionId);
                } else {
                    Log::warning('Payment received for a non-open invoice', ['invoice' => $invoice->number, 'status' => $invoice->status]);
                }
            }

            return $payment;
        });
    }

    /**
     * Mark an invoice paid and activate what it bought. Also used when the admin confirms a bank transfer.
     */
    public function settle(Invoice $invoice, ?string $gateway = null, ?string $reference = null): Invoice
    {
        $this->invoices->markPaid($invoice, $gateway, $reference);

        return $this->activate($invoice, $gateway, $reference);
    }

    /**
     * Give the restaurant the plan the invoice is for. Paying again for the plan it is already on
     * extends it; anything else switches the plan.
     */
    public function activate(Invoice $invoice, ?string $gateway = null, ?string $reference = null): Invoice
    {
        if ($invoice->subscription_id !== null || $invoice->plan_id === null) {
            return $invoice; // already linked (manual assignment) or nothing to activate
        }

        $restaurant = Restaurant::withTrashed()->findOrFail($invoice->restaurant_id);
        $plan = Plan::withTrashed()->findOrFail($invoice->plan_id);
        $current = $this->subscriptions->current($restaurant);

        $subscription = $current && $current->plan_id === $plan->id && $current->status !== 'trialing'
            ? $this->subscriptions->renew($current)
            : $this->subscriptions->assign($restaurant, $plan, null, ['gateway' => $gateway, 'gateway_ref' => $reference]);

        $invoice->update(['subscription_id' => $subscription->id]);

        return $invoice;
    }

    private function amountMatches(Invoice $invoice, PaymentNotification $n): bool
    {
        return $n->amount === Money::toMinor($invoice->total, $invoice->currency_code)
            && strtoupper($n->currency) === strtoupper($invoice->currency_code);
    }
}
