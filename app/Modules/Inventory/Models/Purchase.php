<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id', 'restaurant_id'];
}
