<?php

namespace App\Modules\Orders\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['options' => 'array', 'qty' => 'integer', 'unit_cents' => 'integer', 'total_cents' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** "Large, Extra cheese" */
    public function optionsLabel(): string
    {
        return collect($this->options ?? [])->pluck('name')->implode(', ');
    }
}
