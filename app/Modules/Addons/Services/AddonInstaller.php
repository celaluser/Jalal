<?php

namespace App\Modules\Addons\Services;

use App\Modules\Updater\Exceptions\UpdateException;
use App\Modules\Updater\Services\UpdatePackage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Installs an add-on from a signed package, switches it on (running its migrations) and removes it.
 *
 * A package is an update package (manifest.json with file hashes, manifest.sig, files/...) whose files all live in
 * addons/<slug>/. The signature and every hash are checked before anything is written, and files are written to a temporary
 * folder first, so a failed install never leaves half an add-on behind. New add-ons start switched OFF.
 */
class AddonInstaller
{
    public function __construct(private readonly AddonManager $addons) {}

    /** @return array<string, mixed> the installed add-on's manifest @throws UpdateException|RuntimeException */
    public function install(string $zipPath): array
    {
        $package = UpdatePackage::open($zipPath, (array) config('addons.trusted_keys', []));
        $paths = $package->paths();
        $slug = $this->slugOf($paths);

        $tmp = $this->addons->path().DIRECTORY_SEPARATOR.'.tmp-'.Str::random(8);

        try {
            foreach ($paths as $path) {
                $target = $tmp.DIRECTORY_SEPARATOR.substr($path, strlen("addons/{$slug}/"));
                File::ensureDirectoryExists(dirname($target));
                file_put_contents($target, $package->contents($path));
            }

            $manifest = json_decode((string) @file_get_contents($tmp.'/addon.json'), true);

            if (! is_array($manifest) || ($manifest['slug'] ?? null) !== $slug || ! is_string($manifest['namespace'] ?? null) || ! is_string($manifest['provider'] ?? null)
                || ! preg_match('/^Addons\\\\[A-Za-z0-9\\\\]+\\\\$/', $manifest['namespace']) || ! str_starts_with($manifest['provider'], $manifest['namespace'])) {
                throw new RuntimeException(__('addons.invalid_manifest'));
            }

            if (! $this->addons->compatible($manifest)) {
                throw new RuntimeException(__('addons.needs_core', ['version' => $manifest['min_core']]));
            }

            // Replace an older copy; the old folder is kept until the new one is in place.
            $final = $this->addons->path($slug);
            $old = null;

            if (is_dir($final)) {
                $old = $final.'.old-'.Str::random(6);
                rename($final, $old);
            }

            rename($tmp, $final);

            if ($old) {
                File::deleteDirectory($old);
            }
        } finally {
            File::deleteDirectory($tmp);
        }

        return $manifest;
    }

    /** Switches an add-on on and applies its database migrations. @throws RuntimeException */
    public function enable(string $slug): void
    {
        $manifest = $this->addons->manifest($slug) ?? throw new RuntimeException(__('addons.not_found'));

        if (! $this->addons->compatible($manifest)) {
            throw new RuntimeException(__('addons.needs_core', ['version' => $manifest['min_core']]));
        }

        $this->addons->autoload($slug, $manifest);

        if (! class_exists($manifest['provider'])) {
            throw new RuntimeException(__('addons.provider_missing'));
        }

        $migrations = $this->addons->path($slug).'/database/migrations';

        if (is_dir($migrations)) {
            Artisan::call('migrate', ['--path' => $migrations, '--realpath' => true, '--force' => true]);
        }

        $this->addons->set($slug, true);
    }

    public function disable(string $slug): void
    {
        $this->addons->set($slug, false);
    }

    /** Removes the code of an add-on. Its database tables are left alone, so reinstalling brings the data back. */
    public function uninstall(string $slug): void
    {
        if (! AddonManager::validSlug($slug)) {
            throw new RuntimeException(__('addons.not_found'));
        }

        $this->addons->forget($slug);
        File::deleteDirectory($this->addons->path($slug));
    }

    /** @param list<string> $paths @throws UpdateException */
    private function slugOf(array $paths): string
    {
        if ($paths === [] || ! preg_match('#^addons/([a-z0-9-]+)/#', $paths[0], $m) || ! AddonManager::validSlug($m[1])) {
            throw new UpdateException(__('addons.not_an_addon'));
        }

        foreach ($paths as $path) {
            if (! str_starts_with($path, "addons/{$m[1]}/")) {
                throw new UpdateException(__('addons.not_an_addon'));
            }
        }

        if (! in_array("addons/{$m[1]}/addon.json", $paths, true)) {
            throw new UpdateException(__('addons.invalid_manifest'));
        }

        return $m[1];
    }
}
