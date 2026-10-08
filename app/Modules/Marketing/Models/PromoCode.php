<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Activity\Support\LogsActivity;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCode extends Model
{
    use BelongsToRestaurant, LogsActivity;

    public const PERCENT = 'percent';

    public const FIXED = 'fixed';

    protected $guarded = ['id', 'restaurant_id', 'uses_count'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean', 'value' => 'integer', 'min_order_cents' => 'integer', 'uses_count' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public static function normalize(?string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $code));
    }

    public function isLoyaltyReward(): bool
    {
        return $this->customer_id !== null;
    }

    public function exhausted(): bool
    {
        return $this->max_uses !== null && $this->uses_count >= $this->max_uses;
    }
}
