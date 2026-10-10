<?php

namespace App\Modules\Api\Services;

use App\Modules\Api\Jobs\DeliverWebhook;
use App\Modules\Api\Models\WebhookDelivery;
use App\Modules\Api\Models\WebhookEndpoint;
use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Models\Order;
use App\Modules\Reservations\Events\ReservationBooked;
use App\Modules\Reservations\Events\ReservationStatusChanged;
use App\Modules\Reservations\Models\Reservation;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Str;
use Throwable;

/** Turns order events into signed webhook deliveries (queued, retried, logged). Never lets a webhook problem touch the order flow. */
class WebhookDispatcher
{
    public function __construct(private readonly ApiResources $resources, private readonly LimitGuard $limits) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(OrderPlaced::class, fn (OrderPlaced $e) => $this->order('order.created', $e->order));
        $events->listen(OrderStatusChanged::class, fn (OrderStatusChanged $e) => $this->order('order.status_changed', $e->order, ['previous_status' => $e->from]));
        $events->listen(OrderPaid::class, fn (OrderPaid $e) => $this->order('order.paid', $e->order));
        $events->listen(ReservationBooked::class, fn (ReservationBooked $e) => $this->reservation('reservation.created', $e->reservation));
        $events->listen(ReservationStatusChanged::class, fn (ReservationStatusChanged $e) => $this->reservation('reservation.status_changed', $e->reservation, ['previous_status' => $e->from]));
    }

    /** @param array<string, mixed> $extra */
    public function order(string $event, Order $order, array $extra = []): void
    {
        try {
            $order->loadMissing('items');
            $this->send($order->restaurant_id, $event, ['order' => $this->resources->order($order)] + $extra);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @param array<string, mixed> $extra */
    public function reservation(string $event, Reservation $reservation, array $extra = []): void
    {
        try {
            $this->send($reservation->restaurant_id, $event, ['reservation' => $this->resources->reservation($reservation)] + $extra);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Queues $event for every active endpoint of the restaurant that listens to it (and the plan allows the API). @param array<string, mixed> $data */
    public function send(int $restaurantId, string $event, array $data): int
    {
        $restaurant = Restaurant::find($restaurantId);

        if (! $restaurant || ! $this->limits->hasFeature($restaurant, 'api')) {
            return 0;
        }

        $queued = 0;

        foreach (WebhookEndpoint::withoutGlobalScopes()->where('restaurant_id', $restaurantId)->where('is_active', true)->get() as $endpoint) {
            if ($endpoint->listensTo($event)) {
                $this->queue($endpoint, $event, $data);
                $queued++;
            }
        }

        return $queued;
    }

    /** @param array<string, mixed> $data */
    public function queue(WebhookEndpoint $endpoint, string $event, array $data): WebhookDelivery
    {
        $uuid = (string) Str::uuid();
        $delivery = (new WebhookDelivery(['event' => $event, 'payload' => ['id' => $uuid, 'event' => $event, 'created_at' => now()->toIso8601String(), 'data' => $data]]))
            ->forceFill(['restaurant_id' => $endpoint->restaurant_id, 'webhook_endpoint_id' => $endpoint->id, 'uuid' => $uuid]);
        $delivery->save();
        DeliverWebhook::dispatch($delivery->id);

        return $delivery;
    }
}
