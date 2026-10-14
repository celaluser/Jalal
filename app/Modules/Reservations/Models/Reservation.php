<?php

namespace App\Modules\Reservations\Models;

use App\Modules\Branches\Models\Branch;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use App\Modules\Tables\Models\DiningTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use BelongsToRestaurant;

    /** 'awaiting' = booked but the deposit is not paid yet; the table is held for a few minutes. */
    public const ACTIVE = ['awaiting', 'pending', 'confirmed', 'seated'];

    public const STATUSES = ['awaiting', 'pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show'];

    protected $guarded = ['id', 'restaurant_id', 'token'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'reminded_at' => 'datetime', 'deposit_paid_at' => 'datetime', 'deposit_cents' => 'integer', 'party_size' => 'integer', 'duration_minutes' => 'integer'];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function endsAt()
    {
        return $this->starts_at->copy()->addMinutes($this->duration_minutes);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }
}
