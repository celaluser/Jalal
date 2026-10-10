<?php

namespace App\Modules\Menu\Models;

use App\Modules\Activity\Support\LogsActivity;
use App\Modules\Core\Models\Concerns\HasTranslations;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A named group of categories shown on its own hours: breakfast, lunch, drinks. */
class Menu extends Model
{
    use BelongsToRestaurant, HasTranslations, LogsActivity;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['name' => 'array', 'schedule' => 'array', 'is_active' => 'boolean'];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
}
