<?php

namespace App\Modules\Updater\Services;

use App\Modules\Updater\Exceptions\UpdateException;
use App\Modules\Updater\Models\SystemUpdate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Throwable;
use ZipArchive;

/**
 * Applies a validated package: backup, maintenance mode, copy, migrate, and automatic
 * restore of the previous files if anything fails. Database migrations are not rolled back.
 */
class UpdateApplier
{
    public function __construct(private readonly ?string $basePath = null) {}

    private function root(): string
    {
        return rtrim($this->basePath ?? base_path(), '/');
    }

    public function currentVersion(): string
    {
        return (string) config('version.current');
    }

    public function assertApplicable(UpdatePackage $package): void
    {
        $current = $this->currentVersion();

        if (version_compare($package->version(), $current, '<=')) {
            throw new UpdateException(__('updater.not_newer', ['current' => $current, 'version' => $package->version()]));
        }

        $min = $package->manifest['min_version'] ?? null;

        if ($min && version_compare($current, $min, '<')) {
            throw new UpdateException(__('updater.min_version', ['min' => $min, 'current' => $current]));
        }
    }

    public function apply(UpdatePackage $package, ?int $userId = null, bool $runMigrations = true): SystemUpdate
    {
        $this->assertApplicable($package);

        $record = SystemUpdate::create([
            'version' => $package->version(),
            'from_version' => $this->currentVersion(),
            'status' => 'running',
            'applied_by' => $userId,
        ]);

        $backup = $this->backup($package, $record);
        $record->update(['backup_path' => $backup]);

        $maintenance = $runMigrations;

        try {
            if ($maintenance) {
                Artisan::call('down', ['--retry' => 60]);
            }

            $this->copyFiles($package);

            if ($runMigrations) {
                Artisan::call('migrate', ['--force' => true]);
                Artisan::call('optimize:clear');
            }

            $record->update(['status' => 'applied']);
        } catch (Throwable $e) {
            $this->restore($backup);
            $record->update(['status' => 'rolled_back', 'notes' => $e->getMessage()]);

            throw new UpdateException(__('updater.failed_rolled_back', ['error' => $e->getMessage()]), 0, $e);
        } finally {
            if ($maintenance) {
                Artisan::call('up');
            }
        }

        return $record;
    }

    /**
     * Zip of every existing file the update would overwrite, plus a list of files it creates.
     */
    private function backup(UpdatePackage $package, SystemUpdate $record): string
    {
        $dir = storage_path('app/'.config('updater.storage').'/backups');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/backup-'.$record->from_version.'-to-'.$record->version.'-'.now()->format('YmdHis').'.zip';

        $zip = new ZipArchive;

        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new UpdateException(__('updater.backup_failed'));
        }

        $created = [];

        foreach ($package->paths() as $path) {
            $full = $this->root().'/'.$path;

            if (is_file($full)) {
                $zip->addFile($full, 'files/'.$path);
            } else {
                $created[] = $path;
            }
        }

        $zip->addFromString('created.json', json_encode($created));

        if (! $zip->close()) {
            throw new UpdateException(__('updater.backup_failed'));
        }

        return $file;
    }

    private function copyFiles(UpdatePackage $package): void
    {
        foreach ($package->paths() as $path) {
            $target = $this->root().'/'.$path;
            File::ensureDirectoryExists(dirname($target));

            if (File::put($target, $package->contents($path)) === false) {
                throw new UpdateException(__('updater.write_failed', ['path' => $path]));
            }
        }

        // A changed file must not keep serving stale compiled code.
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    public function restore(string $backupFile): void
    {
        $zip = new ZipArchive;

        if ($zip->open($backupFile) !== true) {
            return;
        }

        foreach (json_decode((string) $zip->getFromName('created.json'), true) ?: [] as $path) {
            UpdatePackage::assertSafePath($path);
            @unlink($this->root().'/'.$path);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (! str_starts_with($name, 'files/')) {
                continue;
            }

            $relative = substr($name, 6);
            UpdatePackage::assertSafePath($relative);
            File::ensureDirectoryExists(dirname($this->root().'/'.$relative));
            File::put($this->root().'/'.$relative, (string) $zip->getFromIndex($i));
        }

        $zip->close();
    }
}
