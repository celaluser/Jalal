<?php

namespace App\Modules\Store\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Entitlement extends Model
{
    use BelongsToRestaurant;

    protected $table = 'store_entitlements';

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('starts_at', '<=', now())->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
