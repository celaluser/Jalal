<?php

namespace App\Modules\Menu\Models;

use App\Modules\Activity\Support\LogsActivity;
use App\Modules\Core\Models\Concerns\HasTranslations;
use App\Modules\Core\Models\Media;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use App\Modules\Menu\Support\DishArt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToRestaurant, HasTranslations, LogsActivity;

    /** Switches that can be flipped from the list without opening the edit form. */
    public const TOGGLES = ['is_active', 'is_available', 'is_featured'];

    public const BADGES = ['new', 'popular', 'chef', 'limited'];

    public const NUTRIENTS = ['protein', 'carbs', 'fat', 'fiber', 'sugar', 'sodium'];

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return [
            'name' => 'array', 'description' => 'array', 'allergens' => 'array', 'dietary' => 'array',
            'price' => 'decimal:2', 'compare_price' => 'decimal:2',
            'is_active' => 'boolean', 'is_available' => 'boolean', 'is_featured' => 'boolean', 'is_combo' => 'boolean',
            'cost_price' => 'decimal:2', 'gallery' => 'array', 'nutrition' => 'array', 'badges' => 'array', 'schedule' => 'array', 'order_types' => 'array', 'limited_until' => 'date',
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

    /** Dishes the owner says go well with this one. */
    public function pairings(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_pairings', 'product_id', 'paired_id')->withPivot('sort')->orderByPivot('sort');
    }

    public function comboSlots(): HasMany
    {
        return $this->hasMany(ComboSlot::class)->orderBy('sort')->orderBy('id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort')->orderBy('id');
    }

    public function optionGroups(): BelongsToMany
    {
        return $this->belongsToMany(OptionGroup::class, 'option_group_product')->withPivot('sort')->orderByPivot('sort');
    }

    /** The dish photo, or the matching illustration when there is none. */
    public function pictureUrl(): string
    {
        return $this->image?->thumbUrl() ?? DishArt::url($this->tr('name'), (string) $this->category?->tr('name'));
    }

    public function tracksStock(): bool
    {
        return $this->stock_qty !== null;
    }

    /** False once a tracked dish has run out. */
    public function inStock(): bool
    {
        return ! $this->tracksStock() || $this->stock_qty > 0;
    }

    /** Orderable right now: switched on by hand and not run out. */
    public function canBeOrdered(): bool
    {
        // A dish whose every size is switched off cannot be ordered either.
        return $this->is_available && $this->inStock() && ($this->variants->isEmpty() || $this->variants->contains('is_available', true));
    }

    public function isLowStock(): bool
    {
        return $this->tracksStock() && $this->stock_qty > 0 && $this->low_stock_at !== null && $this->stock_qty <= $this->low_stock_at;
    }

    public function isOnSale(): bool
    {
        return $this->compare_price !== null && (float) $this->compare_price > (float) $this->price;
    }
}
