<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Plan;
use App\Modules\Store\Services\StoreAccess;
use App\Modules\Tenancy\Models\Restaurant;

/**
 * Single place that answers "may this restaurant add one more X / use feature Y?" from its plan.
 * A restaurant without any current subscription gets no access to limited resources or features.
 *
 *   $guard->canAdd($restaurant, 'products', currentCount: 42)
 *   $guard->hasFeature($restaurant, 'custom_domain')
 */
class LimitGuard
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function plan(Restaurant $restaurant): ?Plan
    {
        return $this->subscriptions->current($restaurant)?->plan;
    }

    /** @return int|null null = unlimited, 0 = nothing allowed */
    public function limit(Restaurant $restaurant, string $key): ?int
    {
        $plan = $this->plan($restaurant);

        return $plan ? $plan->limit($key) : 0;
    }

    public function canAdd(Restaurant $restaurant, string $key, int $currentCount, int $adding = 1): bool
    {
        $limit = $this->limit($restaurant, $key);

        return $limit === null || $currentCount + $adding <= $limit;
    }

    public function remaining(Restaurant $restaurant, string $key, int $currentCount): ?int
    {
        $limit = $this->limit($restaurant, $key);

        return $limit === null ? null : max(0, $limit - $currentCount);
    }

    public function hasFeature(Restaurant $restaurant, string $feature): bool
    {
        // The plan decides first; otherwise the store may give it (free for everyone, bought, tried or granted).
        return (bool) $this->plan($restaurant)?->hasFeature($feature) || app(StoreAccess::class)->featureGranted($restaurant, $feature);
    }
}
