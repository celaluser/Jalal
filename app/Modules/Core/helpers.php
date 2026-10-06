<?php

use App\Modules\Core\Services\SettingsService;
use Illuminate\Support\Str;

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

if (! function_exists('markdown_safe')) {
    /**
     * Markdown to HTML for admin-written content: raw HTML is removed and unsafe link schemes
     * (javascript:, data:) are dropped, so published pages cannot carry scripts.
     */
    function markdown_safe(?string $markdown): string
    {
        return Str::markdown((string) $markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
