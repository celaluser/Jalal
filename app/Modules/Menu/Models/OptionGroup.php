<?php

namespace App\Modules\Menu\Models;

use App\Modules\Activity\Support\LogsActivity;
use App\Modules\Core\Models\Concerns\HasTranslations;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A set of choices a customer makes for a product: "Size" (pick one), "Extras" (pick several). */
class OptionGroup extends Model
{
    use BelongsToRestaurant, HasTranslations, LogsActivity;

    public const TYPES = ['single', 'multiple'];

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['name' => 'array', 'is_required' => 'boolean'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class)->orderBy('sort')->orderBy('id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'option_group_product');
    }
}
