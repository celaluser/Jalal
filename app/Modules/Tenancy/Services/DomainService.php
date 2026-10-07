<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Tenancy\Contracts\DnsResolver;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The addresses a restaurant's menu is reachable at: a subdomain of the platform domain and, on plans that
 * include it, the restaurant's own domain once DNS proves they control it.
 */
class DomainService
{
    /** Names that would collide with the platform itself. */
    public const RESERVED = ['www', 'app', 'admin', 'api', 'mail', 'smtp', 'ftp', 'cdn', 'static', 'assets', 'login', 'register', 'dashboard', 'billing', 'support', 'help', 'status', 'blog', 'docs', 'demo', 'staging', 'test'];

    /** Left of the base domain in the TXT record host, e.g. _qrmenu-verify.menu.example.com. */
    public const TXT_PREFIX = '_qrmenu-verify';

    public function __construct(private readonly DnsResolver $dns, private readonly LimitGuard $limits) {}

    public function subdomainsAvailable(): bool
    {
        return (bool) config('tenancy.subdomains_enabled') && (bool) config('tenancy.base_domain');
    }

    public function customDomainsAvailable(Restaurant $restaurant): bool
    {
        return (bool) config('tenancy.custom_domains_enabled') && $this->limits->hasFeature($restaurant, 'custom_domain');
    }

    /** True when the platform offers custom domains but this plan does not include them. */
    public function customDomainNeedsUpgrade(Restaurant $restaurant): bool
    {
        return (bool) config('tenancy.custom_domains_enabled') && ! $this->limits->hasFeature($restaurant, 'custom_domain');
    }

    /** The value the restaurant must publish in the TXT record: only someone with DNS access can add it. */
    public function verificationToken(Restaurant $restaurant): string
    {
        return 'qrmenu-verify='.substr(hash_hmac('sha256', 'domain:'.$restaurant->id.':'.Str::lower((string) $restaurant->custom_domain), (string) config('app.key')), 0, 32);
    }

    public function txtHost(Restaurant $restaurant): string
    {
        return self::TXT_PREFIX.'.'.$restaurant->custom_domain;
    }

    /** @throws InvalidArgumentException code: reserved | taken | invalid | unavailable */
    public function setSubdomain(Restaurant $restaurant, ?string $subdomain, bool $force = false): void
    {
        if (! $force && ! $this->subdomainsAvailable()) {
            throw new InvalidArgumentException('unavailable');
        }

        $value = $subdomain === null || trim($subdomain) === '' ? null : Str::lower(trim($subdomain));

        if ($value !== null) {
            if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/', $value)) {
                throw new InvalidArgumentException('invalid');
            }

            if (in_array($value, self::RESERVED, true)) {
                throw new InvalidArgumentException('reserved');
            }

            if (Restaurant::withTrashed()->where('subdomain', $value)->whereKeyNot($restaurant->id)->exists()) {
                throw new InvalidArgumentException('taken');
            }
        }

        $restaurant->forceFill(['subdomain' => $value])->save();
    }

    /** Accepts a bare host such as "menu.example.com" (a pasted URL is cleaned up). @throws InvalidArgumentException code: unavailable | invalid | taken | platform */
    public function setCustomDomain(Restaurant $restaurant, ?string $domain, bool $force = false): void
    {
        if (! $force && ! $this->customDomainsAvailable($restaurant)) {
            throw new InvalidArgumentException('unavailable');
        }

        $value = $this->normalizeHost($domain);

        if ($value !== null) {
            if (! preg_match('/^(?=.{4,190}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $value)) {
                throw new InvalidArgumentException('invalid');
            }

            $base = (string) config('tenancy.base_domain');
            if (in_array($value, config('tenancy.central_domains'), true) || ($base !== '' && ($value === $base || str_ends_with($value, '.'.$base)))) {
                throw new InvalidArgumentException('platform');
            }

            if (Restaurant::withTrashed()->where('custom_domain', $value)->whereKeyNot($restaurant->id)->exists()) {
                throw new InvalidArgumentException('taken');
            }
        }

        // A new address starts unverified: the proof was for the old one.
        $changed = $value !== $restaurant->custom_domain;
        $restaurant->forceFill(['custom_domain' => $value] + ($changed ? ['domain_verified_at' => null] : []))->save();
    }

    /** Checks DNS and marks the domain verified. TXT proof of ownership, or a CNAME to the platform domain. */
    public function verify(Restaurant $restaurant): bool
    {
        if (! $restaurant->custom_domain) {
            return false;
        }

        $expected = $this->verificationToken($restaurant);
        $proven = in_array($expected, array_map('trim', $this->dns->txt($this->txtHost($restaurant))), true);

        if (! $proven && ($base = (string) config('tenancy.base_domain')) !== '') {
            $proven = in_array(strtolower($base), $this->dns->cname($restaurant->custom_domain), true);
        }

        if ($proven) {
            $restaurant->forceFill(['domain_verified_at' => now()])->save();
        }

        return $proven;
    }

    public function unverify(Restaurant $restaurant): void
    {
        $restaurant->forceFill(['domain_verified_at' => null])->save();
    }

    public function normalizeHost(?string $domain): ?string
    {
        $value = Str::lower(trim((string) $domain));
        $value = preg_replace('#^https?://#', '', $value);
        $value = Str::before($value, '/');
        $value = rtrim($value, '.');

        return $value === '' ? null : $value;
    }
}
