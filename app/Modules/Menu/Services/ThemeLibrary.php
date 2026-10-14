<?php

namespace App\Modules\Menu\Services;

use App\Modules\Core\Services\SettingsService;
use Illuminate\Support\Str;

/**
 * The menu themes a platform offers: the ones shipped in config/themes.php, edited or switched off by the super admin,
 * plus themes the super admin makes. Stored as one setting (menu_themes) so it needs no table and survives updates.
 * Every value is checked against a fixed list or a hex colour, so a theme can be printed into CSS without escaping.
 */
class ThemeLibrary
{
    public const COLORS = ['bg', 'bg2', 'surface', 'fg', 'muted', 'line'];

    private ?array $stored = null;

    public function __construct(private readonly SettingsService $settings) {}

    /** @return array{themes: array<string, array<string, mixed>>, disabled: list<string>, default: ?string} */
    private function stored(): array
    {
        if ($this->stored === null) {
            $raw = json_decode((string) $this->settings->get('menu_themes', ''), true);
            $raw = is_array($raw) ? $raw : [];
            $this->stored = ['themes' => (array) ($raw['themes'] ?? []), 'disabled' => array_values((array) ($raw['disabled'] ?? [])), 'default' => $raw['default'] ?? null];
        }

        return $this->stored;
    }

    private function persist(array $stored): void
    {
        $this->stored = $stored;
        $this->settings->set('menu_themes', json_encode($stored));
    }

    public function isBuiltin(string $key): bool
    {
        return array_key_exists($key, config('themes.themes'));
    }

    /**
     * Every theme with its effective values.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $stored = $this->stored();
        $out = [];

        foreach (config('themes.themes') as $key => $base) {
            $out[$key] = $this->normalize($key, array_merge($base, (array) ($stored['themes'][$key] ?? [])), true, $stored);
        }

        foreach ($stored['themes'] as $key => $theme) {
            if (! isset($out[$key]) && is_array($theme)) {
                $out[$key] = $this->normalize($key, $theme, false, $stored);
            }
        }

        return $out;
    }

    /** @return array<string, array<string, mixed>> themes restaurants can choose */
    public function enabled(): array
    {
        return array_filter($this->all(), fn ($t) => $t['enabled']);
    }

    public function defaultKey(): string
    {
        $key = $this->stored()['default'] ?? null;
        $enabled = $this->enabled();

        if (is_string($key) && isset($enabled[$key])) {
            return $key;
        }

        return isset($enabled[config('themes.default')]) ? config('themes.default') : (array_key_first($enabled) ?? config('themes.default'));
    }

    /** @param array<string, mixed> $data validated by rules() */
    public function save(string $key, array $data): void
    {
        $stored = $this->stored();
        $stored['themes'][$key] = $this->clean($data);
        $this->persist($stored);
    }

    /** @param array<string, mixed> $data validated by rules() */
    public function create(array $data): string
    {
        do {
            $key = 'c'.Str::lower(Str::random(6));
        } while (isset($this->all()[$key]));

        $this->save($key, $data);

        return $key;
    }

    /** Built-in themes go back to how they shipped; custom themes are deleted. */
    public function reset(string $key): void
    {
        $stored = $this->stored();
        unset($stored['themes'][$key]);

        if (! $this->isBuiltin($key)) {
            $stored['disabled'] = array_values(array_diff($stored['disabled'], [$key]));
            $stored['default'] = $stored['default'] === $key ? null : $stored['default'];
        }

        $this->persist($stored);
    }

    public function toggle(string $key): void
    {
        $stored = $this->stored();
        $off = in_array($key, $stored['disabled'], true);
        $stored['disabled'] = $off ? array_values(array_diff($stored['disabled'], [$key])) : array_values(array_unique([...$stored['disabled'], $key]));

        // The last enabled theme stays on, and a disabled theme cannot be the default.
        $this->persist($stored);

        if (! $this->enabled()) {
            $stored['disabled'] = array_values(array_diff($stored['disabled'], [$key]));
            $this->persist($stored);
        }
    }

    public function setDefault(string $key): void
    {
        $stored = $this->stored();
        $stored['default'] = $key;
        $stored['disabled'] = array_values(array_diff($stored['disabled'], [$key]));
        $this->persist($stored);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $hex = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'name' => ['required', 'string', 'max:40'],
            'dark' => ['nullable', 'boolean'],
            'bg' => $hex, 'bg2' => $hex, 'surface' => $hex, 'fg' => $hex, 'muted' => $hex, 'line' => $hex,
            'font' => ['required', 'in:'.implode(',', array_keys(config('themes.fonts')))],
            'layout' => ['required', 'in:'.implode(',', config('themes.layouts'))],
            'radius' => ['required', 'in:'.implode(',', array_keys(config('themes.radii')))],
            'bg_style' => ['required', 'in:'.implode(',', config('themes.backgrounds'))],
            'card' => ['required', 'in:'.implode(',', config('themes.cards'))],
            'scrollbar' => ['required', 'in:'.implode(',', config('themes.scrollbars'))],
            'reveal' => ['required', 'in:'.implode(',', config('themes.reveals'))],
            'decor' => ['required', 'in:'.implode(',', config('themes.decors'))],
            'button' => ['required', 'in:'.implode(',', config('themes.buttons'))],
            'heading' => ['required', 'in:'.implode(',', config('themes.headings'))],
            'hover' => ['required', 'in:'.implode(',', config('themes.hovers'))],
            'progress' => ['nullable', 'boolean'],
            'animated_bg' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function clean(array $data): array
    {
        $out = ['name' => trim(strip_tags((string) $data['name'])), 'dark' => ! empty($data['dark']), 'progress' => ! empty($data['progress']), 'animated_bg' => ! empty($data['animated_bg'])];

        foreach (self::COLORS as $c) {
            $out[$c] = strtolower((string) $data[$c]);
        }

        foreach (['font', 'layout', 'radius', 'bg_style', 'card', 'scrollbar', 'reveal', 'decor', 'button', 'heading', 'hover'] as $f) {
            $out[$f] = (string) $data[$f];
        }

        return $out;
    }

    /**
     * Fills what an older or partial definition lacks and drops anything invalid, so the CSS is always safe.
     *
     * @param  array<string, mixed>  $t
     * @param  array{disabled: list<string>}  $stored
     * @return array<string, mixed>
     */
    private function normalize(string $key, array $t, bool $builtin, array $stored): array
    {
        $color = fn ($v, $fallback) => is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : $fallback;
        $pick = fn ($v, array $allowed, $fallback) => is_string($v) && in_array($v, $allowed, true) ? $v : $fallback;
        $bg = $color($t['bg'] ?? null, '#ffffff');

        return [
            'key' => $key,
            'name' => (string) ($t['name'] ?? (trans()->has('menu.appearance.themes.'.$key) ? __('menu.appearance.themes.'.$key) : Str::headline($key))),
            'builtin' => $builtin,
            'enabled' => ! in_array($key, $stored['disabled'], true),
            'dark' => (bool) ($t['dark'] ?? false),
            'bg' => $bg, 'bg2' => $color($t['bg2'] ?? null, $bg),
            'surface' => $color($t['surface'] ?? null, '#ffffff'), 'fg' => $color($t['fg'] ?? null, '#111111'),
            'muted' => $color($t['muted'] ?? null, '#666666'), 'line' => $color($t['line'] ?? null, '#dddddd'),
            'font' => $pick($t['font'] ?? null, array_keys(config('themes.fonts')), 'sans'),
            'layout' => $pick($t['layout'] ?? null, config('themes.layouts'), 'cards'),
            'radius' => $pick($t['radius'] ?? null, array_keys(config('themes.radii')), 'soft'),
            'bg_style' => $pick($t['bg_style'] ?? null, config('themes.backgrounds'), 'solid'),
            'card' => $pick($t['card'] ?? null, config('themes.cards'), 'flat'),
            'scrollbar' => $pick($t['scrollbar'] ?? null, config('themes.scrollbars'), 'default'),
            'reveal' => $pick($t['reveal'] ?? null, config('themes.reveals'), 'none'),
            'decor' => $pick($t['decor'] ?? null, config('themes.decors'), 'none'),
            'button' => $pick($t['button'] ?? null, config('themes.buttons'), 'solid'),
            'heading' => $pick($t['heading'] ?? null, config('themes.headings'), 'plain'),
            'hover' => $pick($t['hover'] ?? null, config('themes.hovers'), 'none'),
            'progress' => (bool) ($t['progress'] ?? false),
            'animated_bg' => (bool) ($t['animated_bg'] ?? false),
        ];
    }
}
