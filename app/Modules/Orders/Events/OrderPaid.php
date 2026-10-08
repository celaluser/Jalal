<?php

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Models\Order;

/** The bill of an order is now fully paid (the last payment came in). */
class OrderPaid
{
    public function __construct(public readonly Order $order) {}
}
