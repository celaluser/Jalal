<?php

namespace App\Modules\Ai\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class AiUsage extends Model
{
    use BelongsToRestaurant;

    public $timestamps = false;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
