<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'plan_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    /**
     * Why this coupon cannot be used for the plan right now, or null when it can.
     */
    public function rejectionFor(Plan $plan): ?string
    {
        return match (true) {
            ! $this->is_active => 'coupon.inactive',
            $this->starts_at && $this->starts_at->isFuture() => 'coupon.not_started',
            $this->expires_at && $this->expires_at->isPast() => 'coupon.expired',
            $this->max_uses !== null && $this->used_count >= $this->max_uses => 'coupon.used_up',
            ! empty($this->plan_ids) && ! in_array($plan->id, $this->plan_ids, true) => 'coupon.wrong_plan',
            default => null,
        };
    }

    /**
     * Discount for a subtotal, never more than the subtotal itself.
     */
    public function discountFor(float $subtotal): float
    {
        $discount = $this->type === 'percent'
            ? $subtotal * min((float) $this->value, 100) / 100
            : (float) $this->value;

        return round(min($discount, $subtotal), 2);
    }
}
