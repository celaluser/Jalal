<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Support\UsageRegistry;
use App\Modules\Tenancy\Models\Restaurant;

/** Usage versus plan limits, with the state that drives bars and warnings. */
class UsageReport
{
    /** Share of a limit that triggers the "nearly full" warning. */
    public const WARN_AT = 80;

    public function __construct(private readonly LimitGuard $guard) {}

    /**
     * @return list<array{key: string, used: ?int, limit: ?int, percent: ?int, state: string}> state: ok | warning | full | unlimited
     */
    public function for(Restaurant $restaurant): array
    {
        $plan = $this->guard->plan($restaurant);

        if (! $plan) {
            return [];
        }

        return array_map(function (string $key) use ($plan, $restaurant) {
            $limit = $plan->limit($key);
            $used = UsageRegistry::count($key, $restaurant);
            $percent = $limit !== null && $used !== null ? ($limit === 0 ? 100 : min(100, (int) floor($used / $limit * 100))) : null;

            $state = match (true) {
                $limit === null => 'unlimited',
                $used === null => 'ok',
                $used >= $limit => 'full',
                $percent >= self::WARN_AT => 'warning',
                default => 'ok',
            };

            return compact('key', 'used', 'limit', 'percent', 'state');
        }, Plan::LIMITS);
    }
}
