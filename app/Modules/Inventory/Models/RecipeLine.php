<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeLine extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['qty' => 'float'];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
