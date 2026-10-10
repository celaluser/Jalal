<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

/** A raw material the kitchen buys and uses up: flour, cheese, cola cans. Stock may go below zero when more was used than was entered. */
class Ingredient extends Model
{
    use BelongsToRestaurant;

    public const UNITS = ['kg', 'g', 'l', 'ml', 'pcs'];

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['stock_qty' => 'float', 'low_at' => 'float', 'unit_cost' => 'float'];
    }

    public function isLow(): bool
    {
        return $this->low_at !== null && $this->stock_qty <= $this->low_at;
    }
}
