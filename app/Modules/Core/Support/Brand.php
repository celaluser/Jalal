<?php

namespace App\Modules\Core\Support;

/** The product's own name, logos and icons, as the admin set them in Settings > General. Pages ask here instead of reading settings one by one. */
final class Brand
{
    public static function name(): string
    {
        return (string) config('app.name');
    }

    public static function tagline(): ?string
    {
        return platform_setting('site.tagline');
    }

    /** Logo for light backgrounds. */
    public static function logo(): ?string
    {
        return self::absolute(platform_setting('general.logo'));
    }

    /** Logo for dark backgrounds; the normal logo is used (on a white tile) when no light version was uploaded. */
    public static function logoDark(): ?string
    {
        return self::absolute(platform_setting('general.logo_dark'));
    }

    public static function favicon(): ?string
    {
        return self::absolute(platform_setting('general.favicon'));
    }

    /** The picture shown when a link to the site is shared: its own share image, else the logo. */
    public static function shareImage(): ?string
    {
        return self::absolute(platform_setting('general.og_image')) ?? self::logo();
    }

    public static function color(): string
    {
        return (string) platform_setting('general.brand_color', '#ffb020');
    }

    private static function absolute(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return str_starts_with($url, 'http') || str_starts_with($url, 'data:') ? $url : url($url);
    }
}
