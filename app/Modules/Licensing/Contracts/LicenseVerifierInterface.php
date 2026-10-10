<?php

namespace App\Modules\Licensing\Contracts;

use App\Modules\Licensing\Support\LicenseResult;

/**
 * Verifies a purchase code. Implement this to plug in your own licence server.
 * $product identifies the item ("core" or an add-on alias), $domain the install domain.
 */
interface LicenseVerifierInterface
{
    public function verify(string $purchaseCode, string $domain, string $product = 'core'): LicenseResult;
}
