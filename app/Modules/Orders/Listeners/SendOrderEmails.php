<?php

namespace App\Modules\Orders\Listeners;

use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tables\Qr\QrCode;
use App\Modules\Tables\Qr\QrStyle;
use App\Modules\Tenancy\Models\Restaurant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Events\Dispatcher;

/**
 * E-mails the guest about their order, when they left an address: received, ready, and cancelled by the restaurant.
 * Sent through SafeMail, so a mail outage never affects the order itself.
 */
class SendOrderEmails
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(OrderPlaced::class, fn (OrderPlaced $e) => $this->send($e->order, 'order_received'));
        $events->listen(OrderPaid::class, fn (OrderPaid $e) => $this->sendReceipt($e->order));
        $events->listen(OrderStatusChanged::class, function (OrderStatusChanged $e) {
            match ($e->to) {
                OrderStatus::READY => $this->send($e->order, 'order_ready'),
                // A guest who cancelled their own order does not need to be told.
                OrderStatus::CANCELLED => $e->order->cancel_reason !== __('orders.cancelled_by_guest') ? $this->send($e->order, 'order_cancelled') : null,
                default => null,
            };
        });
    }

    /** The paid receipt as a PDF, once the bill is fully settled. */
    private function sendReceipt(Order $order): void
    {
        if (! $order->customer_email) {
            return;
        }

        $restaurant = Restaurant::withoutGlobalScopes()->find($order->restaurant_id);

        if (! $restaurant) {
            return;
        }

        $order->load(['items', 'payments']);
        $url = $restaurant->publicUrl('order/'.$order->token.'/receipt');

        try {
            $pdf = Pdf::loadView('orders::customer.receipt', [
                'restaurant' => $restaurant, 'order' => $order, 'money' => fn (int $c) => $restaurant->money($c / 100), 'pdf' => true, 'url' => $url,
                'qr' => 'data:image/png;base64,'.base64_encode(QrCode::png($url, new QrStyle('#000000', '#ffffff', 'square'), 300)),
                'tz' => in_array($restaurant->timezone, timezone_identifiers_list(), true) ? $restaurant->timezone : 'UTC',
            ])->setPaper([0, 0, 226.8, 700])->output();
        } catch (\Throwable $e) {
            report($e);
            $pdf = null;
        }

        SafeMail::send($order->customer_email, new TemplatedMail('order_receipt', [
            'name' => $order->customer_name ?: '', 'restaurant' => $restaurant->name, 'number' => '#'.$order->number, 'total' => $restaurant->money($order->total_cents / 100),
            'minutes' => '', 'type' => __('orders.type_'.$order->type), 'status_url' => $url, 'reason' => '',
        ], $order->locale, $pdf ? [[$pdf, 'receipt-'.$order->number.'.pdf']] : []));
    }

    private function send(Order $order, string $template): void
    {
        if (! $order->customer_email) {
            return;
        }

        $restaurant = Restaurant::withoutGlobalScopes()->find($order->restaurant_id);

        if (! $restaurant) {
            return;
        }

        SafeMail::send($order->customer_email, new TemplatedMail($template, [
            'name' => $order->customer_name ?: '', 'restaurant' => $restaurant->name, 'number' => '#'.$order->number,
            'total' => $restaurant->money($order->total_cents / 100), 'minutes' => (string) $order->prep_minutes,
            'type' => __('orders.type_'.$order->type), 'status_url' => $restaurant->publicUrl('order/'.$order->token),
            'reason' => (string) $order->cancel_reason,
        ], $order->locale));
    }
}
