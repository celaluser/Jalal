<?php

namespace App\Modules\Affiliate\Models;

use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A restaurant that joined through another restaurant's link. Platform-level: not tenant scoped. */
class Referral extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reward' => 'decimal:2', 'rewarded_at' => 'datetime'];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'referrer_id')->withTrashed();
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'referred_id')->withTrashed();
    }
}
