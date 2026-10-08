<?php

namespace App\Modules\Orders\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use App\Modules\Orders\Support\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToRestaurant;

    /** Never fillable from a request: identity, numbering and status only change through OrderService. */
    protected $guarded = ['id', 'restaurant_id', 'number', 'token', 'status'];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime', 'dispatched_at' => 'datetime', 'packaging_cents' => 'integer', 'accepted_at' => 'datetime', 'ready_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime', 'paid_at' => 'datetime',
            'subtotal_cents' => 'integer', 'discount_cents' => 'integer', 'marketing_opt_in' => 'boolean', 'service_cents' => 'integer', 'delivery_cents' => 'integer', 'tax_cents' => 'integer', 'total_cents' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
    }

    public function isOpen(): bool
    {
        return OrderStatus::isOpen($this->status);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    /** "#1042": how guests and staff refer to an order. */
    public function label(): string
    {
        return '#'.$this->number;
    }

    /** Whole-unit amount for display helpers. */
    public function amount(int $cents): float
    {
        return $cents / 100;
    }
}
