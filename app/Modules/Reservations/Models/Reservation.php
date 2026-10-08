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

    public const ACTIVE = ['pending', 'confirmed', 'seated'];

    public const STATUSES = ['pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show'];

    protected $guarded = ['id', 'restaurant_id', 'token'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'reminded_at' => 'datetime', 'party_size' => 'integer', 'duration_minutes' => 'integer'];
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
