<?php

namespace App\Modules\Orders\Listeners;

use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderSettings;
use App\Modules\Orders\Services\TicketPrinter;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Events\Dispatcher;
use Throwable;

/** Queues a kitchen ticket when an order arrives and a receipt when it is paid, if the restaurant switched that on. */
class AutoPrint
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(OrderPlaced::class, fn (OrderPlaced $e) => $this->queue($e->order, 'print_auto_kitchen', 'kitchen'));
        $events->listen(OrderPaid::class, fn (OrderPaid $e) => $this->queue($e->order, 'print_auto_receipt', 'receipt'));
    }

    private function queue(Order $order, string $setting, string $kind): void
    {
        try {
            $restaurant = Restaurant::withoutGlobalScopes()->find($order->restaurant_id);

            if ($restaurant && app(OrderSettings::class)->for($restaurant)[$setting]) {
                app(TicketPrinter::class)->enqueue($order, $kind);
            }
        } catch (Throwable $e) {
            report($e); // a printing problem never stops an order
        }
    }
}
