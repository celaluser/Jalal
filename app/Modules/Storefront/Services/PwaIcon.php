<?php

namespace App\Modules\Storefront\Services;

use App\Modules\Core\Models\Media;
use App\Modules\Tables\Qr\QrStyle;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** The home-screen icon of a restaurant's menu app: its logo (or first letter) on the brand colour. Drawn once, then cached. */
class PwaIcon
{
    public const SIZES = [192, 512];

    public function png(Restaurant $restaurant, int $size): string
    {
        $size = in_array($size, self::SIZES, true) ? $size : 192;

        return Cache::remember("pwa-icon:{$restaurant->id}:{$size}:".($restaurant->updated_at?->timestamp ?? 0), 86400, fn () => $this->draw($restaurant, $size));
    }

    private function draw(Restaurant $restaurant, int $size): string
    {
        $canvas = imagecreatetruecolor($size, $size);
        [$r, $g, $b] = QrStyle::rgb($restaurant->brandColor());
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, $r, $g, $b));

        $logo = $this->logo($restaurant);

        if ($logo) {
            // Keep a safe margin: home screens crop icons into circles and squircles.
            $inner = (int) round($size * 0.62);
            $offset = (int) round(($size - $inner) / 2);
            imagecopyresampled($canvas, $logo, $offset, $offset, 0, 0, $inner, $inner, imagesx($logo), imagesy($logo));
        } else {
            $this->letter($canvas, $restaurant, $size);
        }

        ob_start();
        imagepng($canvas);

        return (string) ob_get_clean();
    }

    private function logo(Restaurant $restaurant): ?\GdImage
    {
        try {
            $media = $restaurant->logo_media_id ? Media::find($restaurant->logo_media_id) : null;
            $image = $media ? @imagecreatefromstring(Storage::disk($media->disk)->get($media->path)) : false;

            return $image ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function letter(\GdImage $canvas, Restaurant $restaurant, int $size): void
    {
        $letter = mb_strtoupper(mb_substr(trim($restaurant->name), 0, 1)) ?: 'M';
        // Dark or white ink, whichever reads better on the brand colour.
        $dark = QrStyle::contrast($restaurant->brandColor(), '#0f1115') >= QrStyle::contrast($restaurant->brandColor(), '#ffffff');
        $ink = $dark ? imagecolorallocate($canvas, 15, 17, 21) : imagecolorallocate($canvas, 255, 255, 255);
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');

        if (is_file($font) && function_exists('imagettftext')) {
            $fontSize = $size * 0.42;
            $box = imagettfbbox($fontSize, 0, $font, $letter);
            $x = (int) round(($size - ($box[2] - $box[0])) / 2 - $box[0]);
            $y = (int) round(($size - ($box[1] - $box[7])) / 2 - $box[7]);
            imagettftext($canvas, $fontSize, 0, $x, $y, $ink, $font, $letter);

            return;
        }

        imagestring($canvas, 5, (int) ($size / 2 - 4), (int) ($size / 2 - 8), $letter, $ink);
    }
}
