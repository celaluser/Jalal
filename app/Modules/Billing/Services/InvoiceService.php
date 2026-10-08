<?php

namespace App\Modules\Billing\Services;

use App\Modules\Affiliate\Services\AffiliateService;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Models\Restaurant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Builds invoices. All money maths is done in integer cents so rounding never drifts.
 * Tax comes from platform settings: billing.tax_name, billing.tax_rate (percent) and
 * billing.prices_include_tax.
 */
class InvoiceService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly CouponService $coupons,
        private readonly AffiliateService $affiliate,
    ) {}

    /**
     * @return array{subtotal: int, discount: int, credit: int, tax: int, total: int, rate: float, inclusive: bool} amounts in cents
     */
    public function calculate(float|string $price, ?Coupon $coupon = null, int $creditCents = 0): array
    {
        $subtotal = (int) round(((float) $price) * 100);
        $discount = $coupon ? (int) round($coupon->discountFor($subtotal / 100) * 100) : 0;
        $credit = min(max(0, $creditCents), max(0, $subtotal - $discount)); // unused time on the old plan
        $discount += $credit;
        $net = max(0, $subtotal - $discount);

        $rate = max(0.0, (float) $this->settings->get('billing.tax_rate', 0));
        $inclusive = (bool) $this->settings->get('billing.prices_include_tax', false);

        if ($inclusive) {
            $total = $net;
            $tax = $rate > 0 ? $total - (int) round($total / (1 + $rate / 100)) : 0;
        } else {
            $tax = (int) round($net * $rate / 100);
            $total = $net + $tax;
        }

        return ['subtotal' => $subtotal, 'discount' => $discount, 'credit' => $credit, 'tax' => $tax, 'total' => $total, 'rate' => $rate, 'inclusive' => $inclusive];
    }

    public function create(Restaurant $restaurant, Plan $plan, ?Subscription $subscription = null, ?Coupon $coupon = null, int $creditCents = 0): Invoice
    {
        // Credit earned by referring restaurants comes off after the proration credit, never beyond what is owed.
        $balance = $this->affiliate->creditCents($restaurant, $plan->currency_code);
        $amounts = $this->calculate($plan->price, $coupon, $creditCents + $balance);
        $balanceUsed = min($balance, max(0, $amounts['credit'] - $creditCents));
        $prorated = $amounts['credit'] - $balanceUsed;

        $items = [[
            'description' => $plan->name.' ('.__('billing.interval_'.$plan->interval).')',
            'quantity' => 1,
            'amount' => $amounts['subtotal'] / 100,
        ]];

        if ($prorated > 0) {
            $items[] = ['description' => __('billing.proration_credit'), 'quantity' => 1, 'amount' => -$prorated / 100];
        }

        if ($balanceUsed > 0) {
            $items[] = ['description' => __('billing.referral_credit'), 'quantity' => 1, 'amount' => -$balanceUsed / 100];
        }

        // The invoice number is derived from the existing ones; the unique index is the real guard,
        // so a concurrent duplicate is simply retried with the next number.
        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::transaction(function () use ($restaurant, $subscription, $plan, $amounts, $coupon, $balanceUsed, $items) {
                    $this->affiliate->spend($restaurant, $balanceUsed);

                    return Invoice::create([
                        'restaurant_id' => $restaurant->id,
                        'subscription_id' => $subscription?->id,
                        'plan_id' => $plan->id,
                        'number' => $this->nextNumber(),
                        'status' => $amounts['total'] === 0 ? 'paid' : 'open',
                        'currency_code' => $plan->currency_code,
                        'subtotal' => $amounts['subtotal'] / 100,
                        'discount_amount' => $amounts['discount'] / 100,
                        'credit_used' => $balanceUsed / 100,
                        'tax_name' => $this->settings->get('billing.tax_name', 'VAT'),
                        'tax_rate' => $amounts['rate'],
                        'tax_amount' => $amounts['tax'] / 100,
                        'total' => $amounts['total'] / 100,
                        'coupon_code' => $coupon?->code,
                        'items' => $items,
                        'billing' => $this->billingSnapshot($restaurant),
                        'issued_at' => now(),
                        'due_at' => now()->addDays(7),
                        'paid_at' => $amounts['total'] === 0 ? now() : null,
                    ]);
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }
    }

    public function markPaid(Invoice $invoice, ?string $gateway = null, ?string $reference = null): Invoice
    {
        if ($invoice->status === 'paid') {
            return $invoice;
        }

        $invoice->forceFill(['status' => 'paid', 'paid_at' => now(), 'gateway' => $gateway, 'gateway_ref' => $reference])->save();
        $this->affiliate->onInvoicePaid($invoice);

        if ($invoice->coupon_code && ($coupon = Coupon::where('code', $invoice->coupon_code)->first())) {
            $this->coupons->redeem($coupon, $invoice);
        }

        return $invoice;
    }

    public function void(Invoice $invoice): Invoice
    {
        $invoice->update(['status' => 'void']);
        $this->affiliate->refund($invoice); // credit that was reserved for this invoice goes back

        return $invoice;
    }

    public function pdf(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('billing::invoice-pdf', ['invoice' => $invoice])->setPaper('a4');
    }

    private function nextNumber(): string
    {
        $prefix = config('billing.invoice_prefix').'-'.now()->year.'-';

        $last = Invoice::allTenants()->where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');
        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{seller: array<string, ?string>, buyer: array<string, ?string>}
     */
    private function billingSnapshot(Restaurant $restaurant): array
    {
        return [
            'seller' => [
                'name' => $this->settings->get('billing.company_name', config('app.name')),
                'address' => $this->settings->get('billing.company_address'),
                'tax_id' => $this->settings->get('billing.company_tax_id'),
            ],
            'buyer' => [
                'name' => $restaurant->name,
                'email' => $restaurant->owner?->email,
                'address' => $restaurant->branding['billing_address'] ?? null,
                'tax_id' => $restaurant->branding['tax_id'] ?? null,
            ],
        ];
    }
}
