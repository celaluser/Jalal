<?php

namespace App\Modules\Billing\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use BelongsToRestaurant;

    /** Statuses that grant access to the product. */
    public const ACCESS_STATUSES = ['trialing', 'active', 'past_due'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isOnTrial(): bool
    {
        return $this->status === 'trialing' && $this->trial_ends_at?->isFuture() === true;
    }

    /** Whether the subscription currently grants access (a lifetime plan has no end date). */
    public function grantsAccess(): bool
    {
        return in_array($this->status, self::ACCESS_STATUSES, true)
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }
}
