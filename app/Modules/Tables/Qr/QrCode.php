<?php

namespace App\Modules\Tables\Qr;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use RuntimeException;

/**
 * Renders QR codes as SVG or PNG. Encoding comes from bacon/qr-code; drawing is done here so that
 * no Imagick is needed (shared hosting) and the look (rounded modules, centre logo) is ours.
 */
final class QrCode
{
    /** Modules of empty quiet zone around the code. 4 is the standard; scanners cope with less, not none. */
    private const QUIET = 4;

    /** Side of the centred logo as a share of the code width. With level-H error correction this is safe. */
    private const LOGO_SHARE = 0.22;

    /**
     * @return list<list<bool>> rows of dark modules, without quiet zone
     */
    public static function matrix(string $text, bool $highRedundancy = false): array
    {
        $qr = Encoder::encode($text, ErrorCorrectionLevel::valueOf($highRedundancy ? 'H' : 'M'), 'UTF-8');
        $matrix = $qr->getMatrix();
        $rows = [];

        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            for ($x = 0; $x < $matrix->getWidth(); $x++) {
                $rows[$y][$x] = $matrix->get($x, $y) === 1;
            }
        }

        return $rows;
    }

    public static function svg(string $text, ?QrStyle $style = null): string
    {
        $style ??= new QrStyle;
        $rows = self::matrix($text, $style->logo !== null);
        $n = count($rows);
        $size = $n + 2 * self::QUIET;
        $skip = fn (int $x, int $y) => self::inFinder($x, $y, $n) || ($style->logo !== null && self::inLogo($x, $y, $n));

        $body = '';

        if ($style->shape === 'square') {
            $path = '';

            for ($y = 0; $y < $n; $y++) {
                for ($x = 0; $x < $n; $x++) {
                    if ($rows[$y][$x] && ! ($style->logo !== null && self::inLogo($x, $y, $n))) {
                        $path .= 'M'.($x + self::QUIET).' '.($y + self::QUIET).'h1v1h-1z';
                    }
                }
            }

            $body .= '<path fill="'.$style->fg.'" shape-rendering="crispEdges" d="'.$path.'"/>';
        } else {
            for ($y = 0; $y < $n; $y++) {
                for ($x = 0; $x < $n; $x++) {
                    if ($rows[$y][$x] && ! $skip($x, $y)) {
                        $cx = $x + self::QUIET;
                        $cy = $y + self::QUIET;
                        $body .= $style->shape === 'dots'
                            ? '<circle cx="'.($cx + .5).'" cy="'.($cy + .5).'" r=".5" fill="'.$style->fg.'"/>'
                            : '<rect x="'.$cx.'" y="'.$cy.'" width="1" height="1" rx=".32" fill="'.$style->fg.'"/>';
                    }
                }
            }

            foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$fx, $fy]) {
                $x = $fx + self::QUIET;
                $y = $fy + self::QUIET;
                $body .= '<rect x="'.$x.'" y="'.$y.'" width="7" height="7" rx="2" fill="'.$style->fg.'"/>'
                    .'<rect x="'.($x + 1).'" y="'.($y + 1).'" width="5" height="5" rx="1.2" fill="'.$style->bg.'"/>'
                    .'<rect x="'.($x + 2).'" y="'.($y + 2).'" width="3" height="3" rx=".9" fill="'.$style->fg.'"/>';
            }
        }

        $logo = '';

        if ($style->logo !== null) {
            [$lx, $ly, $side] = self::logoBox($n);
            $lx += self::QUIET;
            $ly += self::QUIET;
            $logo = '<rect x="'.($lx - .6).'" y="'.($ly - .6).'" width="'.($side + 1.2).'" height="'.($side + 1.2).'" rx="1.4" fill="'.$style->bg.'"/>'
                .'<image x="'.$lx.'" y="'.$ly.'" width="'.$side.'" height="'.$side.'" preserveAspectRatio="xMidYMid meet" href="data:image/png;base64,'.base64_encode($style->logo).'"/>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$size.' '.$size.'" width="'.($size * 10).'" height="'.($size * 10).'" role="img">'
            .'<rect width="'.$size.'" height="'.$size.'" fill="'.$style->bg.'"/>'.$body.$logo.'</svg>';
    }

    /** PNG bytes, $pixels wide and tall. Drawn large and scaled down for smooth edges. */
    public static function png(string $text, ?QrStyle $style = null, int $pixels = 1024): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('The GD extension is required to create PNG files.');
        }

        $style ??= new QrStyle;
        $rows = self::matrix($text, $style->logo !== null);
        $n = count($rows);
        $total = $n + 2 * self::QUIET;
        $unit = 24;
        $big = $total * $unit;

        $canvas = imagecreatetruecolor($big, $big);
        $fg = self::allocate($canvas, $style->fg);
        $bg = self::allocate($canvas, $style->bg);
        imagefill($canvas, 0, 0, $bg);

        $rect = fn (float $x, float $y, float $w, float $h, int $color, float $radius = 0) => self::roundedRect($canvas, $x * $unit, $y * $unit, $w * $unit, $h * $unit, $radius * $unit, $color);

        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if (! $rows[$y][$x] || ($style->logo !== null && self::inLogo($x, $y, $n))) {
                    continue;
                }

                if ($style->shape !== 'square' && self::inFinder($x, $y, $n)) {
                    continue;
                }

                $px = $x + self::QUIET;
                $py = $y + self::QUIET;

                match ($style->shape) {
                    'dots' => imagefilledellipse($canvas, (int) (($px + .5) * $unit), (int) (($py + .5) * $unit), (int) ($unit * 1.0), (int) ($unit * 1.0), $fg),
                    'rounded' => $rect($px, $py, 1, 1, $fg, .32),
                    default => $rect($px, $py, 1, 1, $fg),
                };
            }
        }

        if ($style->shape !== 'square') {
            foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$fx, $fy]) {
                $x = $fx + self::QUIET;
                $y = $fy + self::QUIET;
                $rect($x, $y, 7, 7, $fg, 2);
                $rect($x + 1, $y + 1, 5, 5, $bg, 1.2);
                $rect($x + 2, $y + 2, 3, 3, $fg, .9);
            }
        }

        if ($style->logo !== null && ($logo = @imagecreatefromstring($style->logo))) {
            [$lx, $ly, $side] = self::logoBox($n);
            $rect($lx + self::QUIET - .6, $ly + self::QUIET - .6, $side + 1.2, $side + 1.2, $bg, 1.4);
            imagecopyresampled($canvas, $logo, (int) (($lx + self::QUIET) * $unit), (int) (($ly + self::QUIET) * $unit), 0, 0, (int) ($side * $unit), (int) ($side * $unit), imagesx($logo), imagesy($logo));
        }

        $out = imagecreatetruecolor($pixels, $pixels);
        imagecopyresampled($out, $canvas, 0, 0, 0, 0, $pixels, $pixels, $big, $big);

        ob_start();
        imagepng($out, null, 6);

        return (string) ob_get_clean();
    }

    /** The three 7x7 position markers (plus their separator) in the corners. */
    private static function inFinder(int $x, int $y, int $n): bool
    {
        return ($x < 7 && $y < 7) || ($x >= $n - 7 && $y < 7) || ($x < 7 && $y >= $n - 7);
    }

    /** @return array{float, float, float} left, top, side in modules (odd-aligned to the grid) */
    private static function logoBox(int $n): array
    {
        $side = max(3, (int) floor($n * self::LOGO_SHARE));
        $side += ($n - $side) % 2; // keep it centred on the module grid

        return [($n - $side) / 2, ($n - $side) / 2, $side];
    }

    private static function inLogo(int $x, int $y, int $n): bool
    {
        [$lx, $ly, $side] = self::logoBox($n);
        $pad = 1; // clear a one-module margin around the logo plate

        return $x >= $lx - $pad && $x < $lx + $side + $pad && $y >= $ly - $pad && $y < $ly + $side + $pad;
    }

    private static function allocate($image, string $hex): int
    {
        [$r, $g, $b] = QrStyle::rgb($hex);

        return imagecolorallocate($image, $r, $g, $b);
    }

    private static function roundedRect($image, float $x, float $y, float $w, float $h, float $radius, int $color): void
    {
        $x2 = $x + $w - 1;
        $y2 = $y + $h - 1;
        $r = (int) min($radius, $w / 2, $h / 2);

        if ($r <= 0) {
            imagefilledrectangle($image, (int) $x, (int) $y, (int) $x2, (int) $y2, $color);

            return;
        }

        imagefilledrectangle($image, (int) $x + $r, (int) $y, (int) $x2 - $r, (int) $y2, $color);
        imagefilledrectangle($image, (int) $x, (int) $y + $r, (int) $x2, (int) $y2 - $r, $color);
        foreach ([[$x + $r, $y + $r], [$x2 - $r, $y + $r], [$x + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($image, (int) $cx, (int) $cy, $r * 2, $r * 2, $color);
        }
    }
}
