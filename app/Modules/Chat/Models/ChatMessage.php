<?php

namespace App\Modules\Chat\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

/** One line in the conversation between a guest and the staff, tied to the guest's order. */
class ChatMessage extends Model
{
    use BelongsToRestaurant;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'staff_read_at' => 'datetime', 'guest_read_at' => 'datetime'];
    }
}
