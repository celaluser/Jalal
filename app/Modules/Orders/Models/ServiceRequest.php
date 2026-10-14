<?php

namespace App\Modules\Orders\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

/** A guest at a table asking for something: the waiter, the bill, water. */
class ServiceRequest extends Model
{
    use BelongsToRestaurant;

    public const KINDS = ['waiter', 'bill', 'water', 'valet', 'other'];

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['done_at' => 'datetime'];
    }
}
