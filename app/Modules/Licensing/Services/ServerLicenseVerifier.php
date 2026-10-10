<?php

namespace App\Modules\Licensing\Services;

use App\Modules\Licensing\Contracts\LicenseVerifierInterface;
use App\Modules\Licensing\Support\LicenseResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Asks the vendor's own licence server. Expected contract:
 *   POST {server_url}  json {purchase_code, domain, product}
 *   200 {"valid": true, "buyer": "name"}  or  200/4xx {"valid": false, "message": "..."}
 */
class ServerLicenseVerifier implements LicenseVerifierInterface
{
    public function verify(string $purchaseCode, string $domain, string $product = 'core'): LicenseResult
    {
        if (! FormatLicenseVerifier::isWellFormed($purchaseCode)) {
            return LicenseResult::invalid(__('installer.license_bad_format'));
        }

        $url = config('licensing.server_url');

        if (! $url) {
            return LicenseResult::unreachable(__('installer.license_server_not_configured'));
        }

        try {
            $response = Http::timeout(config('licensing.timeout'))
                ->acceptJson()
                ->withHeaders(array_filter(['X-License-Key' => config('licensing.server_key')]))
                ->post($url, ['purchase_code' => trim($purchaseCode), 'domain' => $domain, 'product' => $product]);
        } catch (Throwable $e) {
            return LicenseResult::unreachable($e->getMessage());
        }

        if ($response->serverError()) {
            return LicenseResult::unreachable('HTTP '.$response->status());
        }

        return $response->json('valid') === true
            ? LicenseResult::valid($response->json('buyer'))
            : LicenseResult::invalid($response->json('message') ?: __('installer.license_rejected'));
    }
}
