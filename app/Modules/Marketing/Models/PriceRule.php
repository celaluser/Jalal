<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

/** A time-limited percentage off (happy hour). Applied by the server when a cart is priced. */
class PriceRule extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['days' => 'array', 'percent' => 'integer', 'is_active' => 'boolean', 'category_id' => 'integer'];
    }
}
