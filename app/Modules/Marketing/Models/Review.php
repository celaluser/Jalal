<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use BelongsToRestaurant;

    /** Ratings at or below this are flagged for the restaurant's attention. */
    public const LOW = 2;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'is_public' => 'boolean', 'replied_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isLow(): bool
    {
        return $this->rating <= self::LOW;
    }
}
