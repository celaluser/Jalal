<?php

namespace App\Modules\Menu\Models;

use App\Modules\Core\Models\Concerns\HasTranslations;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One size or portion of a dish with its own price. */
class ProductVariant extends Model
{
    use BelongsToRestaurant, HasTranslations;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['name' => 'array', 'price' => 'decimal:2', 'cost_price' => 'decimal:2', 'is_available' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
