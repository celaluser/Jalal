<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Payments\CheckoutResult;
use App\Modules\Billing\Payments\GatewayManager;
use App\Modules\Billing\Support\Money;
use App\Modules\Tenancy\Models\Restaurant;
use Throwable;

/**
 * Starts paying for a plan: invoice first, then hand over to the chosen gateway.
 */
class CheckoutService
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly InvoiceService $invoices,
        private readonly CouponService $coupons,
        private readonly PaymentProcessor $processor,
    ) {}

    /**
     * @return array{invoice: Invoice, result: CheckoutResult|null} result is null when nothing had to be paid
     *
     * @throws BillingException
     */
    public function start(Restaurant $restaurant, Plan $plan, string $gatewayCode, ?string $couponCode = null): array
    {
        $coupon = $couponCode ? $this->coupons->validate($couponCode, $plan) : null;
        $invoice = $this->invoices->create($restaurant, $plan, null, $coupon);

        // Free plan or a coupon covering everything: no gateway involved.
        if ($invoice->status === 'paid') {
            $this->processor->settle($invoice, 'free');

            return ['invoice' => $invoice->refresh(), 'result' => null];
        }

        $gateway = $this->gateways->find($gatewayCode);

        if (! $gateway || ! $this->gateways->isReady($gatewayCode) || ! $gateway->supportsCurrency($invoice->currency_code)) {
            $this->invoices->void($invoice);

            throw new BillingException(__('billing.gateway_unavailable'));
        }

        try {
            $result = $gateway->checkout(
                $invoice,
                $this->gateways->config($gatewayCode),
                route('billing.return', [$gatewayCode, $invoice->number]),
                route('dashboard'),
                route('webhooks.payments', $gatewayCode),
            );
        } catch (Throwable $e) {
            $this->invoices->void($invoice);
            report($e);

            throw new BillingException(__('billing.gateway_failed'), 0, $e);
        }

        Payment::allTenants()->create([
            'restaurant_id' => $restaurant->id,
            'invoice_id' => $invoice->id,
            'gateway' => $gatewayCode,
            'transaction_id' => $result->reference,
            'amount' => Money::toMinor($invoice->total, $invoice->currency_code),
            'currency_code' => $invoice->currency_code,
            'status' => 'pending',
        ]);

        return ['invoice' => $invoice, 'result' => $result];
    }
}
