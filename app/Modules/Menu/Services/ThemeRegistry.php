<?php

namespace App\Modules\Menu\Services;

use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Store\Services\StoreAccess;
use App\Modules\Tables\Qr\QrStyle;
use App\Modules\Tenancy\Models\Restaurant;

/**
 * Appearance of a restaurant's customer menu: a base theme plus the restaurant's own overrides.
 * The customer menu (Phase 7) injects css() into its layout; the editor previews the same tokens.
 */
class ThemeRegistry
{
    public function __construct(private readonly LimitGuard $limits, private readonly ThemeLibrary $library, private readonly StoreAccess $access) {}

    /** @return array<string, array<string, mixed>> */
    public function themes(): array
    {
        return $this->library->enabled();
    }

    /**
     * Effective settings: theme defaults, overridden by what the restaurant chose.
     *
     * @return array<string, mixed>
     */
    public function settings(Restaurant $restaurant): array
    {
        $key = array_key_exists((string) $restaurant->theme, $this->themes()) && $this->access->themeAllowed($restaurant, $restaurant->theme) ? $restaurant->theme : $this->fallbackFor($restaurant);
        $base = $this->themes()[$key] ?? $this->library->all()[$key];
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
            'currency_switch' => (bool) ($own['currency_switch'] ?? false),
            // Effects start from the theme; the restaurant may choose another one or switch them off.
            'scrollbar' => $this->pick($own['scrollbar'] ?? null, config('themes.scrollbars'), $base['scrollbar']),
            'reveal' => $this->pick($own['reveal'] ?? null, config('themes.reveals'), $base['reveal']),
            'card' => $this->pick($own['card'] ?? null, config('themes.cards'), $base['card']),
            'decor' => $this->pick($own['decor'] ?? null, config('themes.decors'), $base['decor']),
            'button' => $this->pick($own['button'] ?? null, config('themes.buttons'), $base['button']),
            'heading' => $this->pick($own['heading'] ?? null, config('themes.headings'), $base['heading']),
            'hover' => $this->pick($own['hover'] ?? null, config('themes.hovers'), $base['hover']),
            'progress' => isset($own['progress']) && is_bool($own['progress']) ? $own['progress'] : $base['progress'],
            'animated_bg' => isset($own['animated_bg']) && is_bool($own['animated_bg']) ? $own['animated_bg'] : $base['animated_bg'],
            'bg_style' => $base['bg_style'],
            'dark' => $base['dark'],
            // The "Powered by" credit can only be removed on plans that include the feature.
            'show_credit' => ! ($own['hide_credit'] ?? false) || ! $this->canRemoveCredit($restaurant),
        ];
    }

    /** The theme shown when the chosen one is gone, switched off or not (or no longer) unlocked: the default if usable, else the first free one. */
    public function fallbackFor(Restaurant $restaurant): string
    {
        $default = $this->library->defaultKey();

        if ($this->access->themeAllowed($restaurant, $default)) {
            return $default;
        }

        foreach (array_keys($this->themes()) as $key) {
            if ($this->access->themeAllowed($restaurant, $key)) {
                return $key;
            }
        }

        return $default;
    }

    /** Whether the restaurant may choose this theme (it is free, owned, or unlocked by the plan). */
    public function allowed(Restaurant $restaurant, string $theme): bool
    {
        return $this->access->themeAllowed($restaurant, $theme);
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
        $theme = $this->library->all()[$s['theme']];
        $accent = $restaurant->brandColor();
        $inkOnAccent = QrStyle::contrast($accent, '#0f1115') >= QrStyle::contrast($accent, '#ffffff');

        return [
            '--menu-bg' => $theme['bg'],
            '--menu-bg-2' => $theme['bg2'],
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
        $theme = $this->library->all()[$this->settings($restaurant)['theme']];
        $alt = $this->library->all()[$theme['dark'] ? 'modern' : 'midnight'];
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
            'currency_switch' => ! empty($data['currency_switch']),
            'scrollbar' => $data['scrollbar'] ?? null,
            'reveal' => $data['reveal'] ?? null,
            'card' => $data['card'] ?? null,
            'decor' => $data['decor'] ?? null,
            'button' => $data['button'] ?? null,
            'heading' => $data['heading'] ?? null,
            'hover' => $data['hover'] ?? null,
            'progress' => ($data['progress'] ?? '') === '' ? null : $data['progress'] === 'on',
            'animated_bg' => ($data['animated_bg'] ?? '') === '' ? null : $data['animated_bg'] === 'on',
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
            'currency_switch' => ['nullable', 'boolean'],
            'hide_credit' => ['nullable', 'boolean'],
            'scrollbar' => ['nullable', 'in:'.implode(',', config('themes.scrollbars'))],
            'reveal' => ['nullable', 'in:'.implode(',', config('themes.reveals'))],
            'card' => ['nullable', 'in:'.implode(',', config('themes.cards'))],
            'decor' => ['nullable', 'in:'.implode(',', config('themes.decors'))],
            'button' => ['nullable', 'in:'.implode(',', config('themes.buttons'))],
            'heading' => ['nullable', 'in:'.implode(',', config('themes.headings'))],
            'hover' => ['nullable', 'in:'.implode(',', config('themes.hovers'))],
            'progress' => ['nullable', 'in:on,off'],
            'animated_bg' => ['nullable', 'in:on,off'],
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
