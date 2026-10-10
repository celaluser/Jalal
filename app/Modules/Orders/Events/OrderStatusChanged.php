<?php

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Models\Order;

class OrderStatusChanged
{
    public function __construct(public readonly Order $order, public readonly string $from, public readonly string $to) {}
}
