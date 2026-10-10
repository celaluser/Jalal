<?php

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Models\Order;

/** A delivery order left the restaurant with the courier ("on the way"). */
class OrderDispatched
{
    public function __construct(public readonly Order $order) {}
}
