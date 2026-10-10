<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return [
            'marketing_opt_in' => 'boolean', 'opted_in_at' => 'datetime', 'unsubscribed_at' => 'datetime',
            'first_order_at' => 'datetime', 'last_order_at' => 'datetime', 'orders_count' => 'integer', 'total_cents' => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('id');
    }

    /** Can receive marketing e-mail right now: has an address, said yes, and has not unsubscribed. */
    public function canBeEmailed(): bool
    {
        return $this->email !== null && $this->marketing_opt_in && $this->unsubscribed_at === null;
    }

    public function displayName(): string
    {
        return $this->name ?: ($this->email ?: (string) $this->phone);
    }
}
