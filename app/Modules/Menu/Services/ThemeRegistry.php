<?php

namespace App\Modules\Menu\Services;

use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Tables\Qr\QrStyle;
use App\Modules\Tenancy\Models\Restaurant;

/**
 * Appearance of a restaurant's customer menu: a base theme plus the restaurant's own overrides.
 * The customer menu (Phase 7) injects css() into its layout; the editor previews the same tokens.
 */
class ThemeRegistry
{
    public function __construct(private readonly LimitGuard $limits) {}

    /** @return array<string, array<string, mixed>> */
    public function themes(): array
    {
        return config('themes.themes');
    }

    /**
     * Effective settings: theme defaults, overridden by what the restaurant chose.
     *
     * @return array{theme: string, font: string, layout: string, radius: string, show_images: bool, show_credit: bool, hero: string, scroll: string, dark_toggle: bool}
     */
    public function settings(Restaurant $restaurant): array
    {
        $key = array_key_exists($restaurant->theme, $this->themes()) ? $restaurant->theme : config('themes.default');
        $base = $this->themes()[$key];
        $own = (array) ($restaurant->branding['menu'] ?? []);

        return [
            'theme' => $key,
            'font' => $this->pick($own['font'] ?? null, array_keys(config('themes.fonts')), $base['font']),
            'layout' => $this->pick($own['layout'] ?? null, config('themes.layouts'), $base['layout']),
            'radius' => $this->pick($own['radius'] ?? null, array_keys(config('themes.radii')), $base['radius']),
            'show_images' => (bool) ($own['show_images'] ?? true),
            'hero' => $this->pick($own['hero'] ?? null, config('themes.heroes'), 'full'),
            'scroll' => $this->pick($own['scroll'] ?? null, config('themes.scrolls'), 'all'),
            'dark_toggle' => (bool) ($own['dark_toggle'] ?? false),
            // The "Powered by" credit can only be removed on plans that include the feature.
            'show_credit' => ! ($own['hide_credit'] ?? false) || ! $this->canRemoveCredit($restaurant),
        ];
    }

    public function canRemoveCredit(Restaurant $restaurant): bool
    {
        return $this->limits->hasFeature($restaurant, 'remove_branding');
    }

    /**
     * Design tokens for the customer menu. Every value comes from config or is validated, so css()
     * can print them without escaping.
     *
     * @return array<string, string>
     */
    public function tokens(Restaurant $restaurant): array
    {
        $s = $this->settings($restaurant);
        $theme = $this->themes()[$s['theme']];
        $accent = $restaurant->brandColor();
        $inkOnAccent = QrStyle::contrast($accent, '#0f1115') >= QrStyle::contrast($accent, '#ffffff');

        return [
            '--menu-bg' => $theme['bg'],
            '--menu-surface' => $theme['surface'],
            '--menu-fg' => $theme['fg'],
            '--menu-muted' => $theme['muted'],
            '--menu-line' => $theme['line'],
            '--menu-accent' => $accent,
            // Text on a brand-coloured button: whichever of ink/white reads better.
            '--menu-accent-fg' => $inkOnAccent ? '#0f1115' : '#ffffff',
            // Second shade for gradients: lighter under dark text, darker under white text, so contrast holds on both ends.
            '--menu-accent-2' => $this->mix($accent, $inkOnAccent ? '#ffffff' : '#000000', $inkOnAccent ? .38 : .3),
            '--menu-radius' => config('themes.radii')[$s['radius']],
            '--menu-font' => config('themes.fonts')[$s['font']],
        ];
    }

    public function css(Restaurant $restaurant): string
    {
        $declarations = '';

        foreach ($this->tokens($restaurant) as $name => $value) {
            $declarations .= "{$name}:{$value};";
        }

        // Palette a guest switches to with the light/dark button: the opposite of the theme's own.
        $theme = $this->themes()[$this->settings($restaurant)['theme']];
        $alt = $this->themes()[$theme['dark'] ? 'modern' : 'midnight'];
        $swap = '--menu-bg:'.$alt['bg'].';--menu-surface:'.$alt['surface'].';--menu-fg:'.$alt['fg'].';--menu-muted:'.$alt['muted'].';--menu-line:'.$alt['line'].';';

        return ":root{{$declarations}}:root[data-menu-alt]{{$swap}}";
    }

    /** @param array<string, mixed> $data validated by rules() */
    public function save(Restaurant $restaurant, array $data): void
    {
        $branding = $restaurant->branding ?? [];
        $branding['menu'] = [
            'font' => $data['font'],
            'layout' => $data['layout'],
            'radius' => $data['radius'],
            'show_images' => ! empty($data['show_images']),
            'hero' => $data['hero'] ?? 'full',
            'scroll' => $data['scroll'] ?? 'all',
            'dark_toggle' => ! empty($data['dark_toggle']),
            'hide_credit' => ! empty($data['hide_credit']) && $this->canRemoveCredit($restaurant),
        ];

        $restaurant->update(['theme' => $data['theme'], 'branding' => $branding]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'theme' => ['required', 'in:'.implode(',', array_keys($this->themes()))],
            'font' => ['required', 'in:'.implode(',', array_keys(config('themes.fonts')))],
            'layout' => ['required', 'in:'.implode(',', config('themes.layouts'))],
            'radius' => ['required', 'in:'.implode(',', array_keys(config('themes.radii')))],
            'show_images' => ['nullable', 'boolean'],
            'hero' => ['nullable', 'in:'.implode(',', config('themes.heroes'))],
            'scroll' => ['nullable', 'in:'.implode(',', config('themes.scrolls'))],
            'dark_toggle' => ['nullable', 'boolean'],
            'hide_credit' => ['nullable', 'boolean'],
        ];
    }

    private function pick(mixed $value, array $allowed, string $fallback): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
    }

    /** Blend two #rrggbb colours: $amount of $with into $base. */
    private function mix(string $base, string $with, float $amount): string
    {
        [$a, $b] = [QrStyle::rgb($base), QrStyle::rgb($with)];

        return sprintf('#%02x%02x%02x', ...array_map(fn ($i) => (int) round($a[$i] + ($b[$i] - $a[$i]) * $amount), [0, 1, 2]));
    }
}
