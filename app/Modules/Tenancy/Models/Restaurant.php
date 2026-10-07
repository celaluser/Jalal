<?php

namespace App\Modules\Tenancy\Models;

use App\Models\User;
use App\Modules\Core\Models\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A restaurant is the tenant. It is intentionally NOT tenant-scoped itself.
 */
class Restaurant extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'branding' => 'array',
            'domain_verified_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    public function brandColor(): string
    {
        $color = $this->branding['color'] ?? null;

        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#ffb020';
    }

    public function isOnboarded(): bool
    {
        return $this->onboarded_at !== null;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }
}
