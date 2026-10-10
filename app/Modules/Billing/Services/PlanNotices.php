<?php

namespace App\Modules\Billing\Services;

use App\Modules\Tenancy\Models\Restaurant;

/**
 * Things the owner should hear about the subscription, shown as banners across the panel:
 * no plan, trial ending, payment overdue, canceled, and limits that are nearly or fully used.
 */
class PlanNotices
{
    /** Days before a trial or period ends when the banner starts to appear. */
    public const SOON_DAYS = 3;

    public function __construct(private readonly SubscriptionService $subscriptions, private readonly UsageReport $usage) {}

    /**
     * @return list<array{level: string, text: string, cta: ?string}> level: warning | error
     */
    public function for(Restaurant $restaurant): array
    {
        $subscription = $this->subscriptions->current($restaurant);

        if (! $subscription) {
            return [['level' => 'error', 'text' => __('billing.notice_no_plan'), 'cta' => __('billing.choose_plan')]];
        }

        $notices = [];
        $days = $subscription->ends_at ? (int) ceil(now()->diffInHours($subscription->ends_at, false) / 24) : null;

        if ($subscription->status === 'past_due') {
            $notices[] = ['level' => 'error', 'text' => __('billing.notice_past_due'), 'cta' => __('billing.pay_now')];
        } elseif ($subscription->canceled_at && $days !== null) {
            $notices[] = ['level' => 'warning', 'text' => __('billing.notice_canceled', ['date' => $subscription->ends_at->toFormattedDateString()]), 'cta' => __('billing.choose_plan')];
        } elseif ($subscription->status === 'trialing' && $days !== null && $days <= self::SOON_DAYS) {
            $notices[] = ['level' => 'warning', 'text' => trans_choice('billing.notice_trial_ending', max(0, $days), ['days' => max(0, $days)]), 'cta' => __('billing.choose_plan')];
        } elseif ($subscription->status === 'active' && $days !== null && $days <= self::SOON_DAYS) {
            $notices[] = ['level' => 'warning', 'text' => trans_choice('billing.notice_expiring', max(0, $days), ['days' => max(0, $days)]), 'cta' => __('billing.renew')];
        }

        foreach ($this->usage->for($restaurant) as $row) {
            if ($row['state'] === 'full') {
                $notices[] = ['level' => 'error', 'text' => __('billing.notice_limit_full', ['limit' => mb_strtolower(__('admin.plans.limit_'.$row['key'])), 'max' => $row['limit']]), 'cta' => __('billing.upgrade')];
            } elseif ($row['state'] === 'warning') {
                $notices[] = ['level' => 'warning', 'text' => __('billing.notice_limit_near', ['limit' => mb_strtolower(__('admin.plans.limit_'.$row['key'])), 'used' => $row['used'], 'max' => $row['limit']]), 'cta' => __('billing.upgrade')];
            }
        }

        return $notices;
    }
}
