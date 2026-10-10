<?php

namespace App\Modules\Tables\Services;

use App\Modules\Core\Models\Media;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tables\Qr\QrCode;
use App\Modules\Tables\Qr\QrStyle;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** QR codes of a restaurant: what they point to and how they look (set on the QR design screen). */
class TableQr
{
    /** Frames drawn around the code on printed cards. */
    public const FRAMES = ['none', 'border', 'ribbon', 'badge'];

    /**
     * Paper layouts for the PDF: cards per row/page, QR size (mm), paper and orientation.
     * "tent" prints one folded table tent per page (two faces), "poster" one big sign per page.
     */
    public const TEMPLATES = [
        'cards' => ['cols' => 2, 'rows' => 3, 'qr' => 44, 'paper' => 'a4', 'orientation' => 'portrait'],
        'compact' => ['cols' => 3, 'rows' => 4, 'qr' => 32, 'paper' => 'a4', 'orientation' => 'portrait'],
        'sticker' => ['cols' => 4, 'rows' => 6, 'qr' => 36, 'paper' => 'a4', 'orientation' => 'portrait'],
        'tent' => ['cols' => 2, 'rows' => 1, 'qr' => 70, 'paper' => 'a4', 'orientation' => 'landscape'],
        'poster' => ['cols' => 1, 'rows' => 1, 'qr' => 120, 'paper' => 'a4', 'orientation' => 'portrait'],
    ];

    /** Where a scan goes: the table's own entry point, or the plain menu when no table is given. */
    public function url(Restaurant $restaurant, ?DiningTable $table = null): string
    {
        return $restaurant->publicUrl($table ? 't/'.$table->token : '');
    }

    /** @return array{fg: string, bg: string, shape: string, logo: bool, caption: ?string} */
    public function settings(Restaurant $restaurant): array
    {
        $own = (array) ($restaurant->branding['qr'] ?? []);

        return [
            'fg' => $this->color($own['fg'] ?? null, '#0f1115'),
            'bg' => $this->color($own['bg'] ?? null, '#ffffff'),
            'shape' => in_array($own['shape'] ?? null, QrStyle::SHAPES, true) ? $own['shape'] : 'square',
            'logo' => ! empty($own['logo']) && $restaurant->logo_media_id !== null,
            'frame' => in_array($own['frame'] ?? null, self::FRAMES, true) ? $own['frame'] : 'none',
            'template' => in_array($own['template'] ?? null, array_keys(self::TEMPLATES), true) ? $own['template'] : 'cards',
            'caption' => isset($own['caption']) && is_string($own['caption']) && trim($own['caption']) !== '' ? $own['caption'] : null,
        ];
    }

    public function caption(Restaurant $restaurant): string
    {
        return $this->settings($restaurant)['caption'] ?? __('tables.default_caption');
    }

    /** @param array{fg: string, bg: string, shape: string, logo?: bool} $override unsaved values from the design form */
    public function style(Restaurant $restaurant, array $override = []): QrStyle
    {
        $s = array_merge($this->settings($restaurant), $override);
        $useLogo = ! empty($s['logo']) && $restaurant->logo_media_id !== null;

        return new QrStyle($s['fg'], $s['bg'], $s['shape'], $useLogo ? $this->logoPng($restaurant) : null);
    }

    public function svg(Restaurant $restaurant, ?DiningTable $table = null, ?QrStyle $style = null): string
    {
        return QrCode::svg($this->url($restaurant, $table), $style ?? $this->style($restaurant));
    }

    public function png(Restaurant $restaurant, ?DiningTable $table = null, int $pixels = 1024, ?QrStyle $style = null): string
    {
        return QrCode::png($this->url($restaurant, $table), $style ?? $this->style($restaurant), $pixels);
    }

    /** The restaurant logo re-encoded as a small PNG, or null when it cannot be read. */
    private function logoPng(Restaurant $restaurant): ?string
    {
        try {
            $media = Media::find($restaurant->logo_media_id);
            $image = $media ? @imagecreatefromstring(Storage::disk($media->disk)->get($media->path)) : false;

            if (! $image) {
                return null;
            }

            $small = imagecreatetruecolor(256, 256);
            imagealphablending($small, false);
            imagesavealpha($small, true);
            imagefill($small, 0, 0, imagecolorallocatealpha($small, 255, 255, 255, 127));
            imagecopyresampled($small, $image, 0, 0, 0, 0, 256, 256, imagesx($image), imagesy($image));

            ob_start();
            imagepng($small);

            return (string) ob_get_clean();
        } catch (Throwable) {
            return null;
        }
    }

    private function color(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtolower($value) : $fallback;
    }
}
