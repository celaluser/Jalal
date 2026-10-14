<?php

namespace App\Modules\Menu\Models;

use App\Modules\Core\Models\Concerns\HasTranslations;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Option extends Model
{
    use BelongsToRestaurant, HasTranslations;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['name' => 'array', 'price_delta' => 'decimal:2', 'is_available' => 'boolean', 'is_default' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id');
    }
}
