<?php

namespace App\Modules\Storefront\Services;

use App\Modules\Tenancy\Models\Restaurant;

/**
 * The restaurant's small public website (About page) and its search-engine options. Text fields are per language
 * (keyed by locale); the guest sees their own language and falls back to the restaurant's default.
 */
class SiteSettings
{
    public const DEFAULTS = [
        'site_enabled' => false,   // the /about page
        'indexable' => true,       // false = tell search engines to stay away from the menu and the About page
        'seo_title' => [],         // per locale
        'seo_description' => [],
        'about' => [],
        'hours' => [],             // seven lines, Monday (0) to Sunday (6), free text such as "11:00-23:00" or "Closed"
        'map_url' => '',
        'price_range' => '',       // "$", "$$", "$$$"
        'cuisine' => '',           // "Italian, Pizza"
    ];

    /** @return array<string, mixed> */
    public function for(Restaurant $restaurant): array
    {
        $saved = is_array($restaurant->site_settings) ? $restaurant->site_settings : [];

        return array_replace(self::DEFAULTS, array_intersect_key($saved, self::DEFAULTS));
    }

    /** A per-locale text for the guest: their language, else the restaurant's, else the first one filled in. */
    public function text(Restaurant $restaurant, string $key, string $locale): string
    {
        $values = array_filter((array) ($this->for($restaurant)[$key] ?? []), fn ($v) => is_string($v) && trim($v) !== '');

        return (string) ($values[$locale] ?? $values[$restaurant->locale] ?? (reset($values) ?: ''));
    }

    /** @param array<string, mixed> $input validated by rules() */
    public function save(Restaurant $restaurant, array $input): void
    {
        $clean = ['site_enabled' => ! empty($input['site_enabled']), 'indexable' => ! empty($input['indexable'])];

        foreach (['seo_title', 'seo_description', 'about'] as $key) {
            $clean[$key] = collect((array) ($input[$key] ?? []))->map(fn ($v) => trim(strip_tags((string) $v)))->filter()->all();
        }

        $clean['hours'] = collect(range(0, 6))->mapWithKeys(fn ($d) => [$d => mb_substr(trim(strip_tags((string) ($input['hours'][$d] ?? ''))), 0, 60)])->all();
        $clean['map_url'] = trim((string) ($input['map_url'] ?? ''));
        $clean['price_range'] = trim((string) ($input['price_range'] ?? ''));
        $clean['cuisine'] = trim(strip_tags((string) ($input['cuisine'] ?? '')));

        $restaurant->update(['site_settings' => $clean]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'site_enabled' => ['nullable', 'boolean'], 'indexable' => ['nullable', 'boolean'],
            'seo_title' => ['nullable', 'array'], 'seo_title.*' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'array'], 'seo_description.*' => ['nullable', 'string', 'max:170'],
            'about' => ['nullable', 'array'], 'about.*' => ['nullable', 'string', 'max:3000'],
            'hours' => ['nullable', 'array'], 'hours.*' => ['nullable', 'string', 'max:60'],
            'map_url' => ['nullable', 'url:https', 'max:500'], 'price_range' => ['nullable', 'in:$,$$,$$$,$$$$'], 'cuisine' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * schema.org data for search engines: the restaurant with address, phone, hours, cuisine and the link to its menu.
     *
     * @return array<string, mixed>
     */
    public function jsonLd(Restaurant $restaurant, string $locale, ?string $logo, ?array $rating = null): array
    {
        $s = $this->for($restaurant);
        $data = array_filter([
            '@context' => 'https://schema.org', '@type' => 'Restaurant', 'name' => $restaurant->name, 'url' => $restaurant->publicUrl(), 'hasMenu' => $restaurant->publicUrl(),
            'image' => $logo, 'telephone' => $restaurant->phone, 'servesCuisine' => $s['cuisine'] ?: null, 'priceRange' => $s['price_range'] ?: null,
            'description' => $this->text($restaurant, 'seo_description', $locale) ?: null,
            'address' => $restaurant->address || $restaurant->city ? array_filter(['@type' => 'PostalAddress', 'streetAddress' => $restaurant->address, 'addressLocality' => $restaurant->city, 'addressCountry' => $restaurant->country]) : null,
            'openingHours' => $this->openingHours($s['hours']) ?: null,
            'aggregateRating' => $rating ? ['@type' => 'AggregateRating', 'ratingValue' => $rating['average'], 'reviewCount' => $rating['count']] : null,
        ]);

        return $data;
    }

    /** Lines such as "11:00-23:00" become schema.org "Mo 11:00-23:00"; anything else ("Closed", free text) is left out. @return list<string> */
    private function openingHours(array $hours): array
    {
        $days = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
        $out = [];

        foreach ($days as $i => $abbr) {
            if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)\s*[-–]\s*([01]?\d|2[0-3]):([0-5]\d)$/', trim((string) ($hours[$i] ?? '')), $m)) {
                $out[] = sprintf('%s %02d:%s-%02d:%s', $abbr, $m[1], $m[2], $m[3], $m[4]);
            }
        }

        return $out;
    }
}
