<?php

namespace App\Modules\Orders\Models;

use App\Models\User;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One payment towards an order: cash or card on the spot, or an online checkout. A bill can have several (splits, partial payments). */
class OrderPayment extends Model
{
    use BelongsToRestaurant;

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'commission_billed_at' => 'datetime', 'amount_cents' => 'integer', 'tip_cents' => 'integer', 'commission_cents' => 'integer', 'refunded_cents' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** What can still be given back. */
    public function refundable(): int
    {
        return $this->status === self::PAID ? max(0, $this->amount_cents + $this->tip_cents - $this->refunded_cents) : 0;
    }
}
