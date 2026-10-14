<?php

namespace App\Modules\Orders\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

/** A ticket waiting for the restaurant's printer bridge to pick it up. */
class PrintJob extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
