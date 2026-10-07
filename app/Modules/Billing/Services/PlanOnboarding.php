<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Plan;
use App\Modules\Tenancy\Models\Restaurant;

/**
 * Puts a freshly registered restaurant on the plan it picked: a trial if the plan has one, the plan
 * itself if it is free, otherwise it must be paid for (the caller sends the owner to checkout).
 */
class PlanOnboarding
{
    public const TRIAL = 'trial';

    public const FREE = 'free';

    public const CHECKOUT = 'checkout';

    public function __construct(private readonly SubscriptionService $subscriptions) {}

    /** The plan pre-selected on the sign-up form when none was requested. */
    public function defaultPlan(): ?Plan
    {
        $plans = Plan::active()->get();

        return $plans->firstWhere('is_featured', true) ?? $plans->first();
    }

    /** @return self::TRIAL|self::FREE|self::CHECKOUT */
    public function activate(Restaurant $restaurant, Plan $plan): string
    {
        if ($plan->isFree()) {
            $this->subscriptions->assign($restaurant, $plan);

            return self::FREE;
        }

        if ($plan->trial_days > 0) {
            try {
                $this->subscriptions->startTrial($restaurant, $plan);
                $restaurant->update(['trial_ends_at' => now()->addDays($plan->trial_days)]);

                return self::TRIAL;
            } catch (BillingException) {
                // Trial already used: fall through to payment.
            }
        }

        return self::CHECKOUT;
    }
}
