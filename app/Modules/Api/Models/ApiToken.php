<?php

namespace App\Modules\Api\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class ApiToken extends Model
{
    use BelongsToRestaurant;

    public const ABILITIES = ['menu:read', 'menu:write', 'orders:read', 'orders:write', 'reservations:read', 'reservations:write', 'customers:read'];

    protected $guarded = ['id', 'restaurant_id', 'token_hash'];

    protected function casts(): array
    {
        return ['abilities' => 'array', 'last_used_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function can(string $ability): bool
    {
        return in_array($ability, (array) $this->abilities, true);
    }
}
