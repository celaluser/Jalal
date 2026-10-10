<?php

namespace App\Modules\Tables\Qr;

use InvalidArgumentException;

/**
 * How a QR code looks. Colours are validated hex values only, so a style can be embedded into SVG
 * markup without escaping concerns, and the contrast rules keep every printed code scannable.
 */
final class QrStyle
{
    public const SHAPES = ['square', 'rounded', 'dots'];

    /** Minimum WCAG contrast between modules and background; below this phone cameras start to fail. */
    public const MIN_CONTRAST = 4.0;

    public function __construct(
        public readonly string $fg = '#0f1115',
        public readonly string $bg = '#ffffff',
        public readonly string $shape = 'square',
        /** PNG bytes of the logo to place in the centre, or null. */
        public readonly ?string $logo = null,
    ) {
        foreach ([$fg, $bg] as $color) {
            if (! preg_match('/^#[0-9a-f]{6}$/i', $color)) {
                throw new InvalidArgumentException('QR colours must be #rrggbb.');
            }
        }

        if (! in_array($shape, self::SHAPES, true)) {
            throw new InvalidArgumentException('Unknown QR shape.');
        }
    }

    /** Whether modules are clearly darker than the background and contrast enough to scan. */
    public static function isScannable(string $fg, string $bg): bool
    {
        return self::luminance($fg) < self::luminance($bg) && self::contrast($fg, $bg) >= self::MIN_CONTRAST;
    }

    public static function contrast(string $a, string $b): float
    {
        [$hi, $lo] = [max(self::luminance($a), self::luminance($b)), min(self::luminance($a), self::luminance($b))];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    public static function luminance(string $hex): float
    {
        $channels = array_map(function (int $c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, sscanf(ltrim($hex, '#'), '%02x%02x%02x'));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /** @return array{int, int, int} */
    public static function rgb(string $hex): array
    {
        return sscanf(ltrim($hex, '#'), '%02x%02x%02x');
    }

    public function withLogo(?string $png): self
    {
        return new self($this->fg, $this->bg, $this->shape, $png);
    }
}
