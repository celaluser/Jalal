<?php

namespace App\Modules\Affiliate\Services;

use App\Modules\Affiliate\Models\Referral;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Restaurants bring restaurants. Everyone gets a referral link; when someone who joined through it pays their first
 * invoice, the referrer earns account credit (a percentage of that payment) that is taken off their own next invoices.
 * Credit exists only in the platform's billing currency, so it can never be spent on a different currency's invoice.
 */
class AffiliateService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('affiliate.enabled', false);
    }

    public function percent(): float
    {
        return max(0, min(100, (float) $this->settings->get('affiliate.percent', 20)));
    }

    public function currency(): string
    {
        return strtoupper((string) $this->settings->get('billing.currency', 'USD'));
    }

    public function link(Restaurant $restaurant): string
    {
        return route('register', ['ref' => $this->codeFor($restaurant)]);
    }

    public function codeFor(Restaurant $restaurant): string
    {
        if (! $restaurant->referral_code) {
            $restaurant->forceFill(['referral_code' => $this->freshCode()])->saveQuietly();
        }

        return $restaurant->referral_code;
    }

    /** Remembers who referred a new restaurant. Quietly ignores unknown codes and self-referral. */
    public function attach(Restaurant $new, ?string $code): void
    {
        if (! $this->enabled() || ! $code) {
            return;
        }

        $referrer = Restaurant::where('referral_code', strtoupper(trim($code)))->whereKeyNot($new->id)->first();

        if ($referrer) {
            Referral::firstOrCreate(['referred_id' => $new->id], ['referrer_id' => $referrer->id]);
        }
    }

    /** Called when an invoice is paid: the first paid invoice of a referred restaurant rewards the referrer. */
    public function onInvoicePaid(Invoice $invoice): void
    {
        if (! $this->enabled() || (float) $invoice->total <= 0 || strtoupper($invoice->currency_code) !== $this->currency()) {
            return;
        }

        DB::transaction(function () use ($invoice) {
            $referral = Referral::where('referred_id', $invoice->restaurant_id)->where('status', 'pending')->lockForUpdate()->first();

            if (! $referral) {
                return;
            }

            $reward = round((float) $invoice->total * $this->percent() / 100, 2);
            $referral->update(['status' => 'rewarded', 'reward' => $reward, 'rewarded_at' => now()]);
            Restaurant::withTrashed()->whereKey($referral->referrer_id)->increment('billing_credit', $reward);
        });
    }

    /** How much of the restaurant's credit can go on an invoice of this currency, in cents. */
    public function creditCents(Restaurant $restaurant, string $currency): int
    {
        return strtoupper($currency) === $this->currency() ? (int) round(max(0, (float) $restaurant->billing_credit) * 100) : 0;
    }

    public function spend(Restaurant $restaurant, int $cents): void
    {
        if ($cents > 0) {
            Restaurant::whereKey($restaurant->id)->where('billing_credit', '>=', $cents / 100)->decrement('billing_credit', $cents / 100);
        }
    }

    public function refund(Invoice $invoice): void
    {
        if ((float) $invoice->credit_used > 0) {
            Restaurant::withTrashed()->whereKey($invoice->restaurant_id)->increment('billing_credit', (float) $invoice->credit_used);
            $invoice->forceFill(['credit_used' => 0])->saveQuietly();
        }
    }

    private function freshCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Restaurant::withTrashed()->where('referral_code', $code)->exists());

        return $code;
    }
}
