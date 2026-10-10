<?php

namespace App\Modules\Orders\Models;

use App\Models\User;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only history of an order: who did what, when. */
class OrderEvent extends Model
{
    use BelongsToRestaurant;

    public $timestamps = false;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
