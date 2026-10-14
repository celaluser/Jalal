<?php

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Models\Order;

/** Fired after a new order is stored. Notification channels (webhooks, e-mail, printers) listen to this. */
class OrderPlaced
{
    public function __construct(public readonly Order $order) {}
}
