<?php

namespace App\Modules\Menu\Models;

use App\Modules\Core\Models\Concerns\HasTranslations;
use App\Modules\Core\Models\Media;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use App\Modules\Menu\Support\DishArt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use BelongsToRestaurant, HasTranslations;

    /** Switches that can be flipped from the list without opening the edit form. */
    public const TOGGLES = ['is_active', 'is_available', 'is_featured'];

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return [
            'name' => 'array', 'description' => 'array', 'allergens' => 'array', 'dietary' => 'array',
            'price' => 'decimal:2', 'compare_price' => 'decimal:2',
            'is_active' => 'boolean', 'is_available' => 'boolean', 'is_featured' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }

    public function optionGroups(): BelongsToMany
    {
        return $this->belongsToMany(OptionGroup::class, 'option_group_product')->withPivot('sort')->orderByPivot('sort');
    }

    /** The dish photo, or the matching illustration when there is none. */
    public function pictureUrl(): string
    {
        return $this->image?->url() ?? DishArt::url($this->tr('name'), (string) $this->category?->tr('name'));
    }

    public function isOnSale(): bool
    {
        return $this->compare_price !== null && (float) $this->compare_price > (float) $this->price;
    }
}
