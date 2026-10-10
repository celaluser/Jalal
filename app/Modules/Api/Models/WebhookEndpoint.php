<?php

namespace App\Modules\Api\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    use BelongsToRestaurant;

    public const EVENTS = ['order.created', 'order.status_changed', 'order.paid', 'reservation.created', 'reservation.status_changed'];

    /** Consecutive failed deliveries after which an endpoint is switched off. */
    public const MAX_FAILURES = 15;

    protected $guarded = ['id', 'restaurant_id'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'events' => 'array', 'is_active' => 'boolean', 'failures' => 'integer'];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->latest('id');
    }

    public function listensTo(string $event): bool
    {
        $events = (array) $this->events;

        return in_array('*', $events, true) || in_array($event, $events, true);
    }
}
