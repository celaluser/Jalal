<?php

namespace App\Modules\Orders\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

/** A guest's browser, subscribed to hear about one order. Removed when the push service says it is gone. */
class PushSubscription extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id', 'restaurant_id'];
}
