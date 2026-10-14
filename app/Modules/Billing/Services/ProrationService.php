<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Models\Restaurant;

/**
 * Changing plan in the middle of a paid period: the unused part of what the restaurant already paid for
 * is taken off the new invoice. Only a plan that was actually paid for counts (not trials, free or hand-assigned
 * plans), only in the same currency, and only for monthly and yearly periods.
 */
class ProrationService
{
    public function __construct(private readonly SubscriptionService $subscriptions, private readonly SettingsService $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('billing.proration', true);
    }

    /** @return int credit in cents, 0 when none applies */
    public function credit(Restaurant $restaurant, Plan $newPlan): int
    {
        if (! $this->enabled() || $newPlan->isFree()) {
            return 0;
        }

        $current = $this->subscriptions->current($restaurant);

        if (! $current || $current->status !== 'active' || ! $current->ends_at || ! $current->starts_at || ! in_array($current->interval, ['monthly', 'yearly'], true)
            || (float) $current->price <= 0 || $current->plan_id === $newPlan->id || $current->currency_code !== $newPlan->currency_code) {
            return 0;
        }

        $paid = Invoice::allTenants()->where('restaurant_id', $restaurant->id)->where('plan_id', $current->plan_id)->where('status', 'paid')
            ->where('paid_at', '>=', $current->starts_at->copy()->subMinute())->where('total', '>', 0)->latest('id')->first();

        if (! $paid) {
            return 0;
        }

        $period = max(1, $current->starts_at->diffInSeconds($current->ends_at, false));
        $left = max(0, now()->diffInSeconds($current->ends_at, false));

        // Credit what was really paid (tax included), pro rata for the time left, never more than that.
        return (int) min(round($paid->total * 100), floor($paid->total * 100 * $left / $period));
    }
}
