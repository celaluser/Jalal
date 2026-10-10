<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tenancy\Models\Restaurant;

/**
 * How long a new order will take right now: the usual preparation time, plus a few minutes for every order
 * the kitchen is still working through (the owner sets how many; zero keeps the estimate fixed).
 */
class WaitEstimate
{
    public function __construct(private readonly OrderSettings $settings) {}

    /** @return array{minutes: int, base: int, queue: int} */
    public function for(Restaurant $restaurant): array
    {
        $s = $this->settings->for($restaurant);
        $base = (int) $s['prep_minutes'];
        $perOrder = (int) $s['wait_per_order'];
        $queue = $perOrder > 0 ? Order::whereIn('status', [OrderStatus::NEW, OrderStatus::ACCEPTED, OrderStatus::PREPARING])->whereNull('scheduled_for')->count() : 0;

        // Never promise more than an hour extra: a long queue is a reason to pause ordering, not to show "3 hours".
        return ['minutes' => $base + min(60, $queue * $perOrder), 'base' => $base, 'queue' => $queue];
    }
}
