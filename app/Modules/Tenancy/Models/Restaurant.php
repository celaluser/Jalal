<?php

namespace App\Modules\Tenancy\Models;

use App\Models\User;
use App\Modules\Core\Models\Currency;
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
            'menu_locales' => 'array',
            'order_settings' => 'array',
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

    /**
     * Languages customers can pick on the menu, default language first.
     *
     * @return list<string>
     */
    public function menuLocales(): array
    {
        return array_values(array_unique(array_merge([$this->locale ?: config('app.default_locale', 'en')], (array) $this->menu_locales)));
    }

    /** Format a price with the restaurant's currency (symbol, position, separators). */
    public function money(float|int|string $amount): string
    {
        $currency = $this->currency_code ? Currency::where('code', $this->currency_code)->first() : null;

        return $currency ? $currency->format($amount) : number_format((float) $amount, 2);
    }

    /**
     * Address customers use: verified custom domain, then subdomain, then the always-working /r/{slug}.
     * Honors the platform switches so a disabled feature never produces a dead link.
     */
    public function publicUrl(string $path = ''): string
    {
        $path = ltrim($path, '/');
        $secure = str_starts_with((string) config('app.url'), 'https');
        $scheme = $secure ? 'https' : 'http';

        if (config('tenancy.custom_domains_enabled') && $this->custom_domain && $this->domain_verified_at) {
            return "{$scheme}://{$this->custom_domain}".($path !== '' ? "/{$path}" : '');
        }

        if (config('tenancy.subdomains_enabled') && config('tenancy.base_domain') && $this->subdomain) {
            return "{$scheme}://{$this->subdomain}.".config('tenancy.base_domain').($path !== '' ? "/{$path}" : '');
        }

        return url('/'.config('tenancy.path_prefix').'/'.$this->slug.($path !== '' ? "/{$path}" : ''));
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
