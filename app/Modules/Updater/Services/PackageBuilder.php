<?php

namespace App\Modules\Updater\Services;

use App\Modules\Updater\Exceptions\UpdateException;
use ZipArchive;

/**
 * Vendor side: turns a folder into a signed package that UpdatePackage accepts (used by `php artisan package:build`).
 * Run it on YOUR machine; the private key must never be uploaded to a customer's server.
 */
class PackageBuilder
{
    /** @return array{public: string, secret: string} base64 keys */
    public function keys(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return ['public' => base64_encode(sodium_crypto_sign_publickey($pair)), 'secret' => base64_encode(sodium_crypto_sign_secretkey($pair))];
    }

    /**
     * @param  string  $source  folder to package: the project tree for an update, or the add-on folder with $addonSlug
     * @param  string  $secretKey  base64 secret key from keys()
     * @return int number of files packed
     *
     * @throws UpdateException
     */
    public function build(string $source, string $output, string $version, string $secretKey, ?string $notes = null, ?string $addonSlug = null): int
    {
        if (! preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?$/', $version)) {
            throw new UpdateException('Version must look like 1.2.3');
        }

        $secret = base64_decode($secretKey, true);

        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new UpdateException('The secret key is not a valid Ed25519 key.');
        }

        $source = rtrim(realpath($source) ?: '', '/\\');

        if ($source === '' || ! is_dir($source)) {
            throw new UpdateException('Source folder not found.');
        }

        $prefix = $addonSlug ? "addons/{$addonSlug}/" : '';
        $files = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new UpdateException('Symlinks cannot be packaged: '.$file->getPathname());
            }

            if ($file->isFile()) {
                $relative = $prefix.str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
                $files[$relative] = $file->getPathname();
            }
        }

        ksort($files);

        if ($files === []) {
            throw new UpdateException('Nothing to package.');
        }

        $hashes = array_map(fn ($path) => hash_file('sha256', $path), $files);
        $manifest = json_encode(array_filter(['version' => $version, 'notes' => $notes, 'files' => $hashes]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $zip = new ZipArchive;

        if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new UpdateException('Cannot write '.$output);
        }

        $zip->addFromString('manifest.json', $manifest);
        $zip->addFromString('manifest.sig', base64_encode(sodium_crypto_sign_detached($manifest, $secret)));

        foreach ($files as $relative => $path) {
            $zip->addFile($path, 'files/'.$relative);
        }

        $zip->close();

        return count($files);
    }
}
