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
     * @return array{theme: string, font: string, layout: string, radius: string, show_images: bool, show_credit: bool}
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

        return [
            '--menu-bg' => $theme['bg'],
            '--menu-surface' => $theme['surface'],
            '--menu-fg' => $theme['fg'],
            '--menu-muted' => $theme['muted'],
            '--menu-line' => $theme['line'],
            '--menu-accent' => $accent,
            // Text on a brand-coloured button: whichever of ink/white reads better.
            '--menu-accent-fg' => QrStyle::contrast($accent, '#0f1115') >= QrStyle::contrast($accent, '#ffffff') ? '#0f1115' : '#ffffff',
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

        return ":root{{$declarations}}";
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
            'hide_credit' => ['nullable', 'boolean'],
        ];
    }

    private function pick(mixed $value, array $allowed, string $fallback): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
    }
}
