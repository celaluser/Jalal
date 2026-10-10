<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id', 'restaurant_id'];
}
