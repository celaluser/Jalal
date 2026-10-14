<?php

namespace App\Modules\Hub\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

/** A place orders come from besides the QR menu: a delivery platform, a phone bridge, Zapier or Make. Each has its own secret address. */
class OrderChannel extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id', 'restaurant_id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_received_at' => 'datetime'];
    }
}
