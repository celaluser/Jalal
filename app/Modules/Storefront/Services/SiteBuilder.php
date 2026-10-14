<?php

namespace App\Modules\Storefront\Services;

use App\Modules\Core\Models\Media;
use App\Modules\Tenancy\Models\Restaurant;

/**
 * The page builder behind the restaurant's About page: an ordered list of sections (text, image, button, photo gallery,
 * opening hours, contact, social links) and a photo gallery. Texts are per language, like the rest of the site settings.
 * Everything that comes from the editor is cleaned here; only the restaurant's own uploads can be shown.
 */
class SiteBuilder
{
    public const TYPES = ['text', 'image', 'button', 'gallery', 'hours', 'contact', 'follow'];

    public const TARGETS = ['custom', 'menu', 'reserve', 'map', 'phone'];

    public const MAX_BLOCKS = 30;

    public const MAX_PHOTOS = 24;

    /**
     * @return array{blocks: list<array<string, mixed>>, gallery: list<array{media: int, caption: string}>}
     */
    public function clean(Restaurant $restaurant, ?string $blocksJson, ?string $galleryJson): array
    {
        $locales = $restaurant->menuLocales();
        $owned = fn (array $ids) => Media::withoutGlobalScopes()->where('restaurant_id', $restaurant->id)->whereIn('id', $ids)->pluck('id')->map(fn ($i) => (int) $i)->all();

        $blocks = collect($this->decode($blocksJson))->take(self::MAX_BLOCKS)->filter(fn ($b) => is_array($b) && in_array($b['type'] ?? null, self::TYPES, true))->values();
        $photos = collect($this->decode($galleryJson))->filter(fn ($p) => is_array($p) && is_numeric($p['media'] ?? null))->unique(fn ($p) => (int) $p['media'])->take(self::MAX_PHOTOS)->values();

        $valid = $owned($blocks->where('type', 'image')->pluck('media')->merge($photos->pluck('media'))->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v)->all());

        $cleanBlocks = $blocks->map(function (array $b) use ($locales, $valid) {
            return match ($b['type']) {
                'text' => ['type' => 'text', 'title' => $this->texts($b['title'] ?? [], $locales, 120), 'body' => $this->texts($b['body'] ?? [], $locales, 2000)],
                'image' => in_array((int) ($b['media'] ?? 0), $valid, true) ? ['type' => 'image', 'media' => (int) $b['media'], 'caption' => $this->texts($b['caption'] ?? [], $locales, 160)] : null,
                'button' => $this->button($b, $locales),
                default => ['type' => $b['type']],
            };
        })->filter()->values()->all();

        $cleanGallery = $photos->filter(fn ($p) => in_array((int) $p['media'], $valid, true))
            ->map(fn ($p) => ['media' => (int) $p['media'], 'caption' => mb_substr(trim(strip_tags((string) ($p['caption'] ?? ''))), 0, 120)])->values()->all();

        return ['blocks' => $cleanBlocks, 'gallery' => $cleanGallery];
    }

    /**
     * Picture addresses for the media ids used by blocks and gallery.
     *
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    public function urls(array $settings): array
    {
        $ids = collect($settings['blocks'] ?? [])->where('type', 'image')->pluck('media')->merge(collect($settings['gallery'] ?? [])->pluck('media'))->unique()->values()->all();

        return $ids === [] ? [] : Media::withoutGlobalScopes()->whereIn('id', $ids)->get()->mapWithKeys(fn (Media $m) => [$m->id => $m->url()])->all();
    }

    /** @return list<array<string, mixed>> */
    private function decode(?string $json): array
    {
        $data = $json !== null && $json !== '' ? json_decode($json, true) : [];

        return is_array($data) ? array_values($data) : [];
    }

    /**
     * @param  list<string>  $locales
     * @return array<string, string>
     */
    private function texts(mixed $value, array $locales, int $max): array
    {
        return collect(is_array($value) ? $value : [])->filter(fn ($v, $k) => in_array($k, $locales, true) && is_string($v))
            ->map(fn ($v) => mb_substr(trim(strip_tags($v)), 0, $max))->filter(fn ($v) => $v !== '')->all();
    }

    /**
     * @param  array<string, mixed>  $b
     * @param  list<string>  $locales
     * @return array<string, mixed>|null
     */
    private function button(array $b, array $locales): ?array
    {
        $target = in_array($b['target'] ?? null, self::TARGETS, true) ? $b['target'] : 'custom';
        $url = trim((string) ($b['url'] ?? ''));
        $label = $this->texts($b['label'] ?? [], $locales, 40);

        if ($label === [] || ($target === 'custom' && preg_match('#^(https://[^\s]+|tel:\+?[0-9 ()\-]{4,25}|mailto:[^\s@]+@[^\s@]+)$#i', $url) !== 1)) {
            return null;
        }

        return ['type' => 'button', 'label' => $label, 'target' => $target, 'url' => $target === 'custom' ? mb_substr($url, 0, 300) : '', 'style' => ($b['style'] ?? '') === 'dark' ? 'dark' : 'primary'];
    }
}
