<?php

namespace App\Modules\Licensing\Services;

use App\Modules\Licensing\Contracts\LicenseVerifierInterface;
use App\Modules\Licensing\Support\LicenseResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Calls the Envato API directly. Meant to run on the vendor's own licence server. */
class EnvatoLicenseVerifier implements LicenseVerifierInterface
{
    public function verify(string $purchaseCode, string $domain, string $product = 'core'): LicenseResult
    {
        if (! FormatLicenseVerifier::isWellFormed($purchaseCode)) {
            return LicenseResult::invalid(__('installer.license_bad_format'));
        }

        $token = config('licensing.envato_token');

        if (! $token) {
            return LicenseResult::unreachable(__('installer.license_server_not_configured'));
        }

        try {
            $response = Http::timeout(config('licensing.timeout'))
                ->withToken($token)
                ->acceptJson()
                ->get('https://api.envato.com/v3/market/author/sale', ['code' => trim($purchaseCode)]);
        } catch (Throwable $e) {
            return LicenseResult::unreachable($e->getMessage());
        }

        if ($response->status() === 404) {
            return LicenseResult::invalid(__('installer.license_rejected'));
        }

        if (! $response->successful()) {
            return LicenseResult::unreachable('HTTP '.$response->status());
        }

        $itemId = config('licensing.envato_item_id');

        if ($itemId && (string) $response->json('item.id') !== (string) $itemId) {
            return LicenseResult::invalid(__('installer.license_wrong_item'));
        }

        return LicenseResult::valid($response->json('buyer'));
    }
}
