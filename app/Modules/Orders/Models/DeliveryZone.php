<?php

namespace App\Modules\Orders\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

/** A named area the restaurant delivers to, with its own fee and minimum order. */
class DeliveryZone extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'fee' => 'decimal:2', 'min_order' => 'decimal:2'];
    }
}
