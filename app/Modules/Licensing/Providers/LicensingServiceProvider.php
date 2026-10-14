<?php

namespace App\Modules\Licensing\Providers;

use App\Modules\Licensing\Contracts\LicenseVerifierInterface;
use App\Modules\Licensing\Services\EnvatoLicenseVerifier;
use App\Modules\Licensing\Services\FormatLicenseVerifier;
use App\Modules\Licensing\Services\ServerLicenseVerifier;
use Illuminate\Support\ServiceProvider;

class LicensingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LicenseVerifierInterface::class, fn () => match (config('licensing.driver')) {
            'server' => new ServerLicenseVerifier,
            'envato' => new EnvatoLicenseVerifier,
            default => new FormatLicenseVerifier,
        });
    }
}
