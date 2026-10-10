<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

/** A guest's browser that agreed to receive a restaurant's offers as push messages. Removed when the push service says it is gone. */
class PushSubscriber extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id', 'restaurant_id'];
}
