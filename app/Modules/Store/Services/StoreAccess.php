<?php

namespace App\Modules\Store\Services;

use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Tenancy\Models\Restaurant;

/**
 * "May this restaurant use it?" for features and themes, from the store's point of view:
 * free for everyone, bought or granted, or (for themes) unlocked by a plan with premium themes.
 * Plan features themselves are answered by LimitGuard, which asks this class as well.
 */
class StoreAccess
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly Entitlements $entitlements,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /** True when the store (not the plan) gives the feature. */
    public function featureGranted(Restaurant $restaurant, string $feature): bool
    {
        $item = $this->catalog->find('feature:'.$feature);

        if (! $item) {
            return false;
        }

        return $item['mode'] === 'free' || $this->entitlements->owns($restaurant, $item['slug']);
    }

    public function themeAllowed(Restaurant $restaurant, string $theme): bool
    {
        $item = $this->catalog->find('theme:'.$theme);

        if (! $item || $item['mode'] === 'free') {
            return (bool) $item;
        }

        if ($this->entitlements->owns($restaurant, $item['slug'])) {
            return true;
        }

        // A plan (or a purchase) that includes premium themes opens them all.
        return (bool) $this->subscriptions->current($restaurant)?->plan?->hasFeature('premium_themes') || $this->featureGranted($restaurant, 'premium_themes');
    }
}
