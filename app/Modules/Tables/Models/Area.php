<?php

namespace App\Modules\Tables\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id', 'restaurant_id'];

    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class)->orderBy('sort')->orderBy('id');
    }
}
