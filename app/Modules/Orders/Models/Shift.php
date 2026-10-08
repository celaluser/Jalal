<?php

namespace App\Modules\Orders\Models;

use App\Models\User;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A cashier's work period with the cash drawer: counted at the start, counted again at the end, compared with the payments. */
class Shift extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'closed_at' => 'datetime', 'summary' => 'array', 'opening_cents' => 'integer', 'closing_cents' => 'integer', 'expected_cents' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /** Cash counted minus cash expected: negative means the drawer is short. */
    public function difference(): ?int
    {
        return $this->closing_cents === null ? null : $this->closing_cents - (int) $this->expected_cents;
    }
}
