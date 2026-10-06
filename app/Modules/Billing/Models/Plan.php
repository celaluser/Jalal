<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    public const INTERVALS = ['monthly', 'yearly', 'lifetime', 'free'];

    /** Countable limits. A null value means unlimited. */
    public const LIMITS = ['branches', 'tables', 'products', 'categories', 'staff', 'ai_credits'];

    /** On/off feature switches. */
    public const FEATURES = ['custom_domain', 'whatsapp_orders', 'online_payments', 'reservations', 'analytics', 'remove_branding'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'features' => 'array',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort')->orderBy('price');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return int|null null = unlimited */
    public function limit(string $key): ?int
    {
        $value = $this->limits[$key] ?? null;

        return $value === null || $value === '' ? null : (int) $value;
    }

    public function hasFeature(string $key): bool
    {
        return (bool) ($this->features[$key] ?? false);
    }

    public function isFree(): bool
    {
        return $this->interval === 'free' || (float) $this->price <= 0;
    }
}
