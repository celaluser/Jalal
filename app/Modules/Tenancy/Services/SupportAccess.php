<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;

/**
 * The owner's permission for the platform team to sign in as them ("Log in as"). It only matters when the platform
 * switched on security.impersonation_consent: then support access works while the owner has allowed it, for 24 hours at a time.
 */
class SupportAccess
{
    private const KEY = 'support.access_until';

    public function __construct(private readonly SettingsService $settings) {}

    public function required(): bool
    {
        return $this->settings->get('security.impersonation_consent') === '1';
    }

    public function until(Restaurant $restaurant): ?CarbonImmutable
    {
        $value = $this->settings->get(self::KEY, null, $restaurant->id);
        $until = $value ? CarbonImmutable::createFromTimestamp((int) $value) : null;

        return $until && $until->isFuture() ? $until : null;
    }

    public function allowed(Restaurant $restaurant): bool
    {
        return ! $this->required() || $this->until($restaurant) !== null;
    }

    public function grant(Restaurant $restaurant): CarbonImmutable
    {
        $until = CarbonImmutable::now()->addDay();
        $this->settings->set(self::KEY, (string) $until->getTimestamp(), $restaurant->id);

        return $until;
    }

    public function withdraw(Restaurant $restaurant): void
    {
        $this->settings->set(self::KEY, null, $restaurant->id);
    }
}
