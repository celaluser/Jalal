<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

/** A saved audience made of simple rules ("ordered 3+ times, last order over 60 days ago"). Used by campaigns as `custom:<id>`. */
class CustomerSegment extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['rules' => 'array'];
    }
}
