<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Core\Models\Concerns\HasTranslations;
use App\Modules\Core\Models\Media;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A promo banner shown at the top of the guest menu, or a pop-up shown once per visit. */
class Banner extends Model
{
    use BelongsToRestaurant, HasTranslations;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['title' => 'array', 'text' => 'array', 'button' => 'array', 'is_popup' => 'boolean', 'is_active' => 'boolean', 'starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }

    /** Switched on and inside its dates (in the restaurant's time zone, passed in as "today"). */
    public function scopeLive(Builder $query, CarbonImmutable $today): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $today->toDateString()))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today->toDateString()));
    }
}
