<?php

namespace App\Modules\Licensing\Services;

use App\Modules\Licensing\Contracts\LicenseVerifierInterface;
use App\Modules\Licensing\Support\LicenseResult;

/** Accepts any well-formed Envato purchase code (UUID). Development only. */
class FormatLicenseVerifier implements LicenseVerifierInterface
{
    public function verify(string $purchaseCode, string $domain, string $product = 'core'): LicenseResult
    {
        return self::isWellFormed($purchaseCode)
            ? LicenseResult::valid()
            : LicenseResult::invalid(__('installer.license_bad_format'));
    }

    public static function isWellFormed(string $code): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($code));
    }
}
