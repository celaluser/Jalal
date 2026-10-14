<?php

namespace App\Modules\Orders\Listeners;

use App\Modules\Messaging\Services\Messenger;
use App\Modules\Orders\Events\OrderDispatched;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\PushNotifier;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\App;

/** Tells a guest who asked for it (SMS, WhatsApp or browser push) when the order is ready or the courier is on the way. */
class NotifyGuest
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(OrderStatusChanged::class, function (OrderStatusChanged $e) {
            if ($e->to === OrderStatus::READY) {
                $this->notify($e->order, 'notify_ready_'.$e->order->type);
            }
        });
        $events->listen(OrderDispatched::class, fn (OrderDispatched $e) => $this->notify($e->order, 'notify_on_the_way'));
    }

    private function notify(Order $order, string $key): void
    {
        if (! $order->notify_channel) {
            return;
        }

        $restaurant = Restaurant::withoutGlobalScopes()->find($order->restaurant_id);

        if (! $restaurant) {
            return;
        }

        $previous = App::getLocale();
        if ($order->locale && preg_match('/^[a-z]{2}(_[A-Za-z]{2})?$/', $order->locale) && is_dir(lang_path($order->locale))) {
            App::setLocale($order->locale);
        }

        try {
            $text = __('orders.'.$key, ['restaurant' => $restaurant->name, 'number' => '#'.$order->number]);
            $url = $restaurant->publicUrl('order/'.$order->token);

            match ($order->notify_channel) {
                'sms', 'whatsapp' => $order->customer_phone ? app(Messenger::class)->send($restaurant, $order->notify_channel, $order->customer_phone, $text.' '.$url, 'order') : null,
                'push' => app(PushNotifier::class)->send($order, $restaurant->name, $text, $url),
                default => null,
            };
        } finally {
            App::setLocale($previous);
        }
    }
}
