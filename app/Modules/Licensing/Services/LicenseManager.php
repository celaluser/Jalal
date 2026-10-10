<?php

namespace App\Modules\Licensing\Services;

use App\Modules\Core\Services\SettingsService;
use App\Modules\Licensing\Contracts\LicenseVerifierInterface;
use App\Modules\Licensing\Support\LicenseResult;

/**
 * Verifies a purchase code and records the result in platform settings.
 * Add-ons call verify() with their own $product alias.
 */
class LicenseManager
{
    public function __construct(
        private readonly LicenseVerifierInterface $verifier,
        private readonly SettingsService $settings,
    ) {}

    public function verify(string $purchaseCode, string $product = 'core'): LicenseResult
    {
        return $this->verifier->verify($purchaseCode, parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost', $product);
    }

    /**
     * Persist the outcome. An unreachable server stores the code as "unverified" so installation
     * is never blocked by someone else's outage.
     */
    public function record(string $purchaseCode, LicenseResult $result, string $product = 'core'): void
    {
        $this->settings->set("license.{$product}.code", trim($purchaseCode), null, true);
        $this->settings->set("license.{$product}.status", $result->valid ? 'verified' : 'unverified');
        $this->settings->set("license.{$product}.checked_at", now()->toIso8601String());

        if ($result->buyer) {
            $this->settings->set("license.{$product}.buyer", $result->buyer);
        }
    }

    public function status(string $product = 'core'): string
    {
        return (string) $this->settings->get("license.{$product}.status", 'none');
    }
}
