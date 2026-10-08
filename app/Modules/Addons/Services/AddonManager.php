<?php

namespace App\Modules\Addons\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Finds add-ons on disk, knows which are switched on, and boots the enabled ones.
 *
 * An add-on's addon.json:
 *   {"slug":"hello","name":"Hello","version":"1.0.0","author":"You","description":"...","min_core":"1.0.0",
 *    "namespace":"Addons\\Hello\\","provider":"Addons\\Hello\\HelloServiceProvider"}
 * Its classes live in addons/<slug>/src (PSR-4 for the namespace). Migrations in addons/<slug>/database/migrations.
 */
class AddonManager
{
    /** @var array<string, string> slug => error, for add-ons that failed to start in this request */
    private array $errors = [];

    public function path(?string $slug = null): string
    {
        return rtrim((string) config('addons.path'), '/\\').($slug ? DIRECTORY_SEPARATOR.$slug : '');
    }

    public static function validSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) && strlen($slug) >= 3 && strlen($slug) <= 40;
    }

    /** @return array<string, array<string, mixed>> slug => manifest, for every well-formed add-on folder */
    public function all(): array
    {
        $found = [];

        foreach (glob($this->path().'/*/addon.json') ?: [] as $file) {
            $manifest = json_decode((string) file_get_contents($file), true);
            $slug = basename(dirname($file));

            if ($this->wellFormed($manifest, $slug)) {
                $found[$slug] = $manifest;
            }
        }

        ksort($found);

        return $found;
    }

    public function manifest(string $slug): ?array
    {
        return self::validSlug($slug) ? ($this->all()[$slug] ?? null) : null;
    }

    public function enabled(string $slug): bool
    {
        return (bool) ($this->state()[$slug] ?? false);
    }

    public function error(string $slug): ?string
    {
        return $this->errors[$slug] ?? null;
    }

    /** Switches an add-on on or off. Does not run migrations; AddonInstaller does that on enable. */
    public function set(string $slug, bool $on): void
    {
        $state = $this->state();
        $state[$slug] = $on;
        $this->write($state);
    }

    public function forget(string $slug): void
    {
        $state = $this->state();
        unset($state[$slug]);
        $this->write($state);
    }

    /** Loads the code of one add-on (autoloading for its namespace) without starting it. */
    public function autoload(string $slug, array $manifest): void
    {
        $prefix = $manifest['namespace'];
        $dir = $this->path($slug).DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR;

        spl_autoload_register(function (string $class) use ($prefix, $dir) {
            if (str_starts_with($class, $prefix)) {
                $file = $dir.str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))).'.php';

                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
    }

    /** Registers the service providers of all enabled add-ons. A broken add-on is skipped and reported, never fatal. */
    public function boot(): void
    {
        foreach ($this->all() as $slug => $manifest) {
            if (! $this->enabled($slug)) {
                continue;
            }

            try {
                if (! $this->compatible($manifest)) {
                    $this->errors[$slug] = __('addons.needs_core', ['version' => $manifest['min_core']]);

                    continue;
                }

                $this->autoload($slug, $manifest);

                if (! class_exists($manifest['provider'])) {
                    $this->errors[$slug] = __('addons.provider_missing');

                    continue;
                }

                app()->register($manifest['provider']);
            } catch (Throwable $e) {
                report($e);
                $this->errors[$slug] = mb_substr($e->getMessage(), 0, 200);
            }
        }
    }

    public function compatible(array $manifest): bool
    {
        return version_compare((string) config('version.current', '0.0.0'), (string) ($manifest['min_core'] ?? '0.0.0'), '>=');
    }

    private function wellFormed(mixed $m, string $slug): bool
    {
        return is_array($m) && self::validSlug($slug) && ($m['slug'] ?? null) === $slug && is_string($m['name'] ?? null) && is_string($m['version'] ?? null)
            && preg_match('/^\d+\.\d+\.\d+$/', $m['version']) && is_string($m['namespace'] ?? null) && preg_match('/^Addons\\\\[A-Za-z0-9\\\\]+\\\\$/', $m['namespace'])
            && is_string($m['provider'] ?? null) && str_starts_with($m['provider'], $m['namespace']);
    }

    /** @return array<string, bool> */
    private function state(): array
    {
        $file = (string) config('addons.state_file');
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];

        return is_array($data) ? $data : [];
    }

    /** @param array<string, bool> $state */
    private function write(array $state): void
    {
        $file = (string) config('addons.state_file');
        File::ensureDirectoryExists(dirname($file));
        $tmp = $file.'.'.Str::random(6).'.tmp';
        file_put_contents($tmp, json_encode($state, JSON_PRETTY_PRINT));
        rename($tmp, $file);
    }
}
