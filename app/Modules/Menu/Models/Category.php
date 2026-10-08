<?php

namespace App\Modules\Menu\Models;

use App\Modules\Activity\Support\LogsActivity;
use App\Modules\Core\Models\Concerns\HasTranslations;
use App\Modules\Core\Models\Media;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToRestaurant, HasTranslations, LogsActivity;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'is_active' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('sort')->orderBy('id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }
}
