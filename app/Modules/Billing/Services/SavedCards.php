<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Contracts\SavesCards;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\SavedCard;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Payments\GatewayManager;
use App\Modules\Billing\Payments\PaymentNotification;
use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Tenancy\Models\Restaurant;
use Throwable;

/**
 * Keeps the card a restaurant paid with (when it ticked "renew automatically") and charges it for the next period.
 * A refused charge never gives the plan away: the invoice is voided, the owner is told, and after three failed days
 * automatic renewal is switched off. The plan simply runs out as before.
 */
class SavedCards
{
    public const MAX_FAILURES = 3;

    /** How many days before the end the renewal is attempted. */
    public const DAYS_BEFORE = 1;

    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly InvoiceService $invoices,
        private readonly PaymentProcessor $processor,
    ) {}

    /** After a settled payment that asked for it: look the card up at the gateway and keep it. */
    public function capture(PaymentNotification $n): void
    {
        $gateway = $this->gateways->find($n->gateway);

        if (! $gateway instanceof SavesCards || ! $this->gateways->isReady($n->gateway)) {
            return;
        }

        $invoice = Invoice::allTenants()->where('number', $n->invoiceNumber)->first();
        $card = $invoice ? $gateway->cardFromPayment($n->transactionId, $this->gateways->config($n->gateway)) : null;

        if (! $invoice || ! $card) {
            return;
        }

        $model = SavedCard::withoutGlobalScopes()->where('restaurant_id', $invoice->restaurant_id)->first() ?? (new SavedCard)->forceFill(['restaurant_id' => $invoice->restaurant_id]);
        $model->forceFill([
            'gateway' => $n->gateway, 'customer_id' => $card['customer'], 'method_id' => $card['method'], 'brand' => $card['brand'], 'last4' => $card['last4'],
            'exp_month' => $card['exp_month'], 'exp_year' => $card['exp_year'], 'auto_renew' => true, 'failed_attempts' => 0, 'last_attempt_at' => null,
        ])->save();
    }

    /** Subscriptions that are about to end (or just ended) and have a card kept for them. @return list<array{0: Subscription, 1: SavedCard}> */
    public function due(): array
    {
        $out = [];

        Subscription::allTenants()->with('plan')->whereIn('status', ['active', 'past_due'])->whereNull('canceled_at')->whereNotNull('ends_at')
            ->where('ends_at', '<=', now()->addDays(self::DAYS_BEFORE))->orderBy('id')->each(function (Subscription $sub) use (&$out) {
                $card = SavedCard::withoutGlobalScopes()->where('restaurant_id', $sub->restaurant_id)->where('auto_renew', true)->where('failed_attempts', '<', self::MAX_FAILURES)->first();
                $plan = $sub->plan;

                // One try per day, never for a free or lifetime plan, a card that has expired, or a plan that is gone.
                if ($card && $plan && ! $plan->trashed() && ! $plan->isFree() && in_array($plan->interval, ['monthly', 'yearly'], true) && $card->validOn(now())
                    && ($card->last_attempt_at === null || $card->last_attempt_at->lt(now()->subHours(20)))) {
                    $out[] = [$sub, $card];
                }
            });

        return $out;
    }

    /** Charges one renewal. Returns true when the plan was extended. */
    public function renew(Subscription $sub, SavedCard $card): bool
    {
        $restaurant = Restaurant::withTrashed()->find($sub->restaurant_id);
        $plan = $sub->plan;
        $gateway = $this->gateways->find($card->gateway);

        if (! $restaurant || $restaurant->isSuspended() || ! $gateway instanceof SavesCards || ! $this->gateways->isReady($card->gateway) || ! $gateway->supportsCurrency($plan->currency_code)) {
            return false;
        }

        $card->forceFill(['last_attempt_at' => now()])->save();

        return app(TenantContext::class)->runAs($restaurant, function () use ($restaurant, $plan, $card, $gateway) {
            $invoice = $this->invoices->create($restaurant, $plan);

            if ($invoice->status === 'paid') { // a coupon or credit covered everything
                $this->processor->settle($invoice, 'credit');

                return true;
            }

            try {
                $notification = $gateway->chargeSaved($invoice, $card, $this->gateways->config($card->gateway));
            } catch (Throwable $e) {
                report($e);
                $notification = new PaymentNotification($card->gateway, $invoice->number, 'failed-'.$invoice->number, PaymentNotification::FAILED, 0, $invoice->currency_code, ['error' => 'exception']);
            }

            $this->processor->handle($notification);

            if ($invoice->fresh()->status === 'paid') {
                $card->forceFill(['failed_attempts' => 0])->save();

                return true;
            }

            $this->invoices->void($invoice->fresh());
            $this->failed($restaurant, $card, (string) ($notification->payload['error'] ?? 'declined'));

            return false;
        });
    }

    /** @return array{renewed: int, failed: int} */
    public function renewDue(): array
    {
        $done = ['renewed' => 0, 'failed' => 0];

        foreach ($this->due() as [$sub, $card]) {
            $this->renew($sub, $card) ? $done['renewed']++ : $done['failed']++;
        }

        return $done;
    }

    private function failed(Restaurant $restaurant, SavedCard $card, string $reason): void
    {
        $attempts = $card->failed_attempts + 1;
        $card->forceFill(['failed_attempts' => $attempts, 'auto_renew' => $attempts < self::MAX_FAILURES])->save();

        $owner = $restaurant->owner;

        if ($owner) {
            SafeMail::send($owner, new TemplatedMail('card_failed', [
                'name' => $owner->name, 'restaurant' => $restaurant->name, 'card' => trim(($card->brand ? ucfirst($card->brand).' ' : '').'•••• '.$card->last4),
                'reason' => $reason, 'billing_url' => route('billing.index'),
                'attempts_left' => (string) max(0, self::MAX_FAILURES - $attempts),
            ], $owner->locale));
        }
    }
}
