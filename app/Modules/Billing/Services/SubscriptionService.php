<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Subscription lifecycle. Platform code (and each restaurant's own billing screens) reach
 * subscriptions by explicit restaurant id, never through the ambient tenant context.
 */
class SubscriptionService
{
    /**
     * The subscription that currently grants access, if any.
     */
    public function current(Restaurant $restaurant): ?Subscription
    {
        return Subscription::allTenants()
            ->where('restaurant_id', $restaurant->id)
            ->whereIn('status', Subscription::ACCESS_STATUSES)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest('id')
            ->first();
    }

    public function history(Restaurant $restaurant)
    {
        return Subscription::allTenants()->with('plan')->where('restaurant_id', $restaurant->id)->latest('id')->get();
    }

    /**
     * Start a trial on a plan. A restaurant gets one trial per plan.
     */
    public function startTrial(Restaurant $restaurant, Plan $plan): Subscription
    {
        $alreadyTried = Subscription::allTenants()
            ->where('restaurant_id', $restaurant->id)->where('plan_id', $plan->id)->whereNotNull('trial_ends_at')->exists();

        if ($plan->trial_days < 1 || $alreadyTried) {
            throw new BillingException(__('billing.no_trial'));
        }

        return $this->replaceWith($restaurant, $plan, [
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays($plan->trial_days),
            'ends_at' => now()->addDays($plan->trial_days),
        ]);
    }

    /**
     * Put a restaurant on a plan (manual assignment by the admin, or after a successful payment).
     * Any running subscription is closed first, so there is only ever one current subscription.
     */
    public function assign(Restaurant $restaurant, Plan $plan, ?Carbon $endsAt = null, array $extra = []): Subscription
    {
        return $this->replaceWith($restaurant, $plan, array_merge([
            'status' => 'active',
            'ends_at' => $endsAt ?? $this->endFor($plan, now()),
        ], $extra));
    }

    /**
     * Extend the current period by one interval, counted from the old end date when it is still ahead.
     */
    public function renew(Subscription $subscription): Subscription
    {
        $from = $subscription->ends_at && $subscription->ends_at->isFuture() ? $subscription->ends_at : now();

        $subscription->forceFill([
            'status' => 'active',
            'canceled_at' => null,
            'reminder_sent_at' => null,
            'ends_at' => $this->endFor($subscription->plan, $from, $subscription->interval),
        ])->save();

        return $subscription;
    }

    /**
     * Cancel. By default access continues until the paid period ends; immediately=true stops it now.
     */
    public function cancel(Subscription $subscription, bool $immediately = false): Subscription
    {
        $subscription->canceled_at = now();

        if ($immediately) {
            $subscription->status = 'canceled';
            $subscription->ends_at = now();
        }

        $subscription->save();

        return $subscription;
    }

    /**
     * Daily housekeeping: trials and canceled subscriptions expire at their end date, unpaid active
     * ones get a grace period (past_due) and then expire. Returns how many rows changed.
     */
    public function expireDue(): int
    {
        $changed = 0;
        $grace = now()->subDays(config('billing.grace_days'));

        Subscription::allTenants()->whereIn('status', ['trialing', 'active', 'past_due'])
            ->whereNotNull('ends_at')->where('ends_at', '<=', now())->each(function (Subscription $sub) use ($grace, &$changed) {
                $stopsNow = $sub->status === 'trialing' || $sub->canceled_at !== null || $sub->ends_at->lte($grace);
                $next = $stopsNow ? 'expired' : 'past_due';

                if ($sub->status !== $next) {
                    $sub->update(['status' => $next]);
                    $changed++;
                }
            });

        return $changed;
    }

    public function endFor(Plan $plan, Carbon $from, ?string $interval = null): ?Carbon
    {
        return match ($interval ?? $plan->interval) {
            'monthly' => $from->copy()->addMonth(),
            'yearly' => $from->copy()->addYear(),
            default => null, // lifetime and free never end
        };
    }

    private function replaceWith(Restaurant $restaurant, Plan $plan, array $attributes): Subscription
    {
        return DB::transaction(function () use ($restaurant, $plan, $attributes) {
            Subscription::allTenants()
                ->where('restaurant_id', $restaurant->id)
                ->whereIn('status', Subscription::ACCESS_STATUSES)
                ->update(['status' => 'canceled', 'ends_at' => now(), 'canceled_at' => now()]);

            return Subscription::create(array_merge([
                'restaurant_id' => $restaurant->id,
                'plan_id' => $plan->id,
                'interval' => $plan->interval,
                'price' => $plan->price,
                'currency_code' => $plan->currency_code,
                'starts_at' => now(),
            ], $attributes));
        });
    }
}
