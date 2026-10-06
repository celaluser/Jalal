<?php

namespace App\Modules\Updater\Services;

use App\Modules\Updater\Exceptions\UpdateException;
use ZipArchive;

/**
 * A validated update zip. Layout:
 *   manifest.json   {"version","min_version","name","notes","files":{"app/Foo.php":"<sha256>", ...}}
 *   manifest.sig    base64 Ed25519 detached signature of manifest.json
 *   files/...       payload, mirroring the project tree
 *
 * Nothing is trusted until open() has checked signature, paths, sizes and every file hash.
 */
class UpdatePackage
{
    /**
     * @param  array<string, mixed>  $manifest
     */
    private function __construct(
        private readonly ZipArchive $zip,
        public readonly array $manifest,
    ) {}

    public function __destruct()
    {
        @$this->zip->close();
    }

    public static function open(string $path): self
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new UpdateException(__('updater.invalid_zip'));
        }

        self::guardArchive($zip);

        $manifestRaw = $zip->getFromName('manifest.json');
        $manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;

        if (! is_array($manifest) || ! isset($manifest['version'], $manifest['files']) || ! is_array($manifest['files'])
            || ! preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?$/', (string) $manifest['version'])) {
            throw new UpdateException(__('updater.invalid_manifest'));
        }

        self::verifySignature($manifestRaw, (string) $zip->getFromName('manifest.sig'));

        $package = new self($zip, $manifest);
        $package->verifyFiles();

        return $package;
    }

    public function version(): string
    {
        return $this->manifest['version'];
    }

    public function notes(): ?string
    {
        return $this->manifest['notes'] ?? null;
    }

    /**
     * @return list<string> project-relative paths this update writes
     */
    public function paths(): array
    {
        return array_keys($this->manifest['files']);
    }

    public function contents(string $path): string
    {
        $data = $this->zip->getFromName('files/'.$path);

        if ($data === false) {
            throw new UpdateException(__('updater.file_missing', ['path' => $path]));
        }

        return $data;
    }

    /** Checks that apply to the archive itself, before any entry is read. */
    private static function guardArchive(ZipArchive $zip): void
    {
        if ($zip->numFiles > config('updater.max_entries')) {
            throw new UpdateException(__('updater.too_large'));
        }

        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $total += $stat['size'];

            $zip->getExternalAttributesIndex($i, $opsys, $attr);

            if ($opsys === ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000) {
                throw new UpdateException(__('updater.symlink', ['path' => $stat['name']]));
            }
        }

        if ($total > config('updater.max_uncompressed_bytes')) {
            throw new UpdateException(__('updater.too_large'));
        }
    }

    private static function verifySignature(string $manifestRaw, string $signatureB64): void
    {
        $key = config('updater.public_key');

        if (! $key) {
            if (config('updater.allow_unsigned')) {
                return;
            }

            throw new UpdateException(__('updater.no_public_key'));
        }

        $signature = base64_decode(trim($signatureB64), true);
        $publicKey = base64_decode($key, true);

        $valid = $signature !== false && $publicKey !== false
            && strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES
            && strlen($publicKey) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            && sodium_crypto_sign_verify_detached($signature, $manifestRaw, $publicKey);

        if (! $valid) {
            throw new UpdateException(__('updater.bad_signature'));
        }
    }

    private function verifyFiles(): void
    {
        foreach ($this->manifest['files'] as $path => $hash) {
            self::assertSafePath((string) $path);

            if (! is_string($hash) || ! hash_equals(strtolower($hash), hash('sha256', $this->contents($path)))) {
                throw new UpdateException(__('updater.hash_mismatch', ['path' => $path]));
            }
        }
    }

    /**
     * A path must be relative, free of traversal, inside an allowed root and not protected.
     */
    public static function assertSafePath(string $path): void
    {
        $segments = explode('/', $path);

        $unsafe = $path === ''
            || str_contains($path, "\0")
            || str_contains($path, '\\')
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:/', $path)
            || in_array('..', $segments, true)
            || in_array('.', $segments, true)
            || in_array('', $segments, true);

        if ($unsafe) {
            throw new UpdateException(__('updater.unsafe_path', ['path' => $path]));
        }

        if (! in_array($segments[0], config('updater.allowed_roots'), true)) {
            throw new UpdateException(__('updater.path_not_allowed', ['path' => $path]));
        }

        foreach (config('updater.protected') as $protected) {
            if ($path === $protected || str_starts_with($path, $protected.'/')) {
                throw new UpdateException(__('updater.path_not_allowed', ['path' => $path]));
            }
        }
    }
}
