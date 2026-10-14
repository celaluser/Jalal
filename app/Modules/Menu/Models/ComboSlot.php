<?php

namespace App\Modules\Menu\Models;

use App\Modules\Core\Models\Concerns\HasTranslations;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One choice a set menu asks for: "Pick a main", "Pick a drink". */
class ComboSlot extends Model
{
    use BelongsToRestaurant, HasTranslations;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['name' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ComboSlotItem::class)->orderBy('sort')->orderBy('id');
    }
}
