<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class GiftCard extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id', 'restaurant_id', 'balance_cents'];

    protected function casts(): array
    {
        return ['initial_cents' => 'integer', 'balance_cents' => 'integer', 'expires_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function usable(): bool
    {
        return $this->is_active && $this->balance_cents > 0 && ! ($this->expires_at && $this->expires_at->isPast());
    }
}
