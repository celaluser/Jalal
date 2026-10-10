<?php

namespace App\Modules\Core\Support;

/**
 * Turns the two colours an admin picks (primary and accent) into the full palette the design system
 * uses (--color-brand-50..900, --color-accent-50..900), plus the text colour that stays readable on
 * the primary, and link colours that always reach AA contrast on the page background.
 *
 * Only validated #rrggbb values go in and only derived #rrggbb values come out, so the result can be
 * printed into a <style> tag without escaping.
 */
final class BrandPalette
{
    public const DEFAULT_BRAND = '#ffb020';

    public const DEFAULT_ACCENT = '#1c8e73';

    /** Share mixed in for each step: positive = towards white, negative = towards black. */
    private const STEPS = [50 => .92, 100 => .82, 200 => .66, 300 => .46, 400 => .22, 500 => 0, 600 => -.12, 700 => -.28, 800 => -.44, 900 => -.6];

    private const INK = '#0f1115';

    public static function valid(?string $hex): bool
    {
        return is_string($hex) && (bool) preg_match('/^#[0-9a-f]{6}$/i', $hex);
    }

    /** @return array<int, string> shade => #rrggbb */
    public static function shades(string $base): array
    {
        $base = strtolower($base);

        return array_map(fn (float $mix) => $mix === 0.0 ? $base : self::mix($base, $mix > 0 ? '#ffffff' : '#000000', abs($mix)), self::STEPS);
    }

    /** Ink or white, whichever reads better on this colour. */
    public static function onColor(string $hex): string
    {
        return self::contrast($hex, self::INK) >= self::contrast($hex, '#ffffff') ? self::INK : '#ffffff';
    }

    /** The colour darkened (or lightened) just enough to reach $ratio against $background: for links and small text. */
    public static function readable(string $hex, string $background, float $ratio = 4.5): string
    {
        $towards = self::luminance($background) > .5 ? '#000000' : '#ffffff';

        for ($amount = 0.0; $amount <= 1.0; $amount += .04) {
            $candidate = self::mix($hex, $towards, $amount);

            if (self::contrast($candidate, $background) >= $ratio) {
                return $candidate;
            }
        }

        return $towards;
    }

    /** CSS that overrides the default palette. Empty when both colours are the defaults. */
    public static function css(?string $brand, ?string $accent): string
    {
        $brand = self::valid($brand) ? strtolower($brand) : self::DEFAULT_BRAND;
        $accent = self::valid($accent) ? strtolower($accent) : self::DEFAULT_ACCENT;

        if ($brand === self::DEFAULT_BRAND && $accent === self::DEFAULT_ACCENT) {
            return '';
        }

        $vars = '';
        foreach (['brand' => $brand, 'accent' => $accent] as $name => $base) {
            foreach (self::shades($base) as $step => $value) {
                $vars .= "--color-{$name}-{$step}:{$value};";
            }
        }

        $vars .= '--on-brand:'.self::onColor($brand).';';
        $light = self::readable($accent, '#ffffff');
        $dark = self::readable($accent, '#14171d');

        return ":root{{$vars}--link:{$light};}:root.dark{--link:{$dark};}";
    }

    public static function contrast(string $a, string $b): float
    {
        [$hi, $lo] = [max(self::luminance($a), self::luminance($b)), min(self::luminance($a), self::luminance($b))];

        return ($hi + .05) / ($lo + .05);
    }

    private static function luminance(string $hex): float
    {
        $c = array_map(function (int $v) {
            $v /= 255;

            return $v <= .03928 ? $v / 12.92 : (($v + .055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return .2126 * $c[0] + .7152 * $c[1] + .0722 * $c[2];
    }

    /** @return array{int, int, int} */
    private static function rgb(string $hex): array
    {
        return sscanf(ltrim($hex, '#'), '%02x%02x%02x');
    }

    private static function mix(string $base, string $with, float $amount): string
    {
        [$a, $b] = [self::rgb($base), self::rgb($with)];

        return sprintf('#%02x%02x%02x', ...array_map(fn ($i) => (int) round($a[$i] + ($b[$i] - $a[$i]) * $amount), [0, 1, 2]));
    }
}
