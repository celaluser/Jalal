<?php

use App\Modules\Core\Services\SettingsService;

if (! function_exists('platform_setting')) {
    /**
     * Read a platform-level setting (cached). Handy in Blade: {{ platform_setting('general.logo') }}
     */
    function platform_setting(string $key, mixed $default = null): mixed
    {
        $value = app(SettingsService::class)->get($key);

        return $value === null || $value === '' ? $default : $value;
    }
}
