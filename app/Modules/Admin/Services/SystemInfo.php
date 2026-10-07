<?php

namespace App\Modules\Admin\Services;

use App\Modules\Core\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only facts about the installation for the System page: environment, limits that bite on
 * shared hosting, and whether the scheduler and queue worker are actually running.
 */
class SystemInfo
{
    public const SCHEDULER_KEY = 'system.scheduler_heartbeat';

    public const QUEUE_KEY = 'system.queue_heartbeat';

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return array<string, string>
     */
    public function environment(): array
    {
        return [
            'app_version' => (string) config('version.current'),
            'laravel' => app()->version(),
            'php' => PHP_VERSION.' ('.PHP_SAPI.')',
            'environment' => (string) config('app.env'),
            'debug' => config('app.debug') ? 'on' : 'off',
            'url' => (string) config('app.url'),
            'timezone' => (string) config('app.timezone'),
            'database' => $this->database(),
            'cache' => (string) config('cache.default'),
            'queue' => (string) config('queue.default'),
            'session' => (string) config('session.driver'),
            'mail' => (string) config('mail.default'),
            'storage' => (string) $this->settings->get('storage.disk', 'public'),
            'license' => (string) $this->settings->get('license.core.status', 'none'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function limits(): array
    {
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());

        return [
            'memory_limit' => (string) ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time').'s',
            'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
            'post_max_size' => (string) ini_get('post_max_size'),
            'disk' => $free !== false && $total ? $this->bytes($free).' free of '.$this->bytes($total) : 'unknown',
        ];
    }

    /**
     * @return list<array{label: string, ok: bool}>
     */
    public function checks(): array
    {
        $checks = [];

        foreach (config('installer.extensions') as $extension) {
            $checks[] = ['label' => "PHP {$extension}", 'ok' => extension_loaded($extension)];
        }

        foreach (config('installer.writable') as $path) {
            $checks[] = ['label' => $path, 'ok' => is_dir(base_path($path)) && is_writable(base_path($path))];
        }

        $checks[] = ['label' => 'WebP (GD)', 'ok' => function_exists('imagewebp')];
        $checks[] = ['label' => 'APP_DEBUG off in production', 'ok' => ! (config('app.env') === 'production' && config('app.debug'))];

        return $checks;
    }

    /**
     * Scheduler and queue worker status, from heartbeats they write themselves.
     *
     * @return array{scheduler: array{state: string, seconds: ?int}, queue: array{state: string, seconds: ?int}, pending: ?int, failed: ?int}
     */
    public function workers(): array
    {
        return [
            'scheduler' => $this->heartbeat(self::SCHEDULER_KEY, ok: 150, warn: 900),
            'queue' => $this->heartbeat(self::QUEUE_KEY, ok: 900, warn: 7200),
            'pending' => $this->count('jobs'),
            'failed' => $this->count('failed_jobs'),
        ];
    }

    public static function beat(string $key): void
    {
        Cache::forever($key, now()->timestamp);
    }

    /**
     * @return array{state: string, seconds: ?int} state: ok | stale | never
     */
    private function heartbeat(string $key, int $ok, int $warn): array
    {
        $at = Cache::get($key);

        if ($at === null) {
            return ['state' => 'never', 'seconds' => null];
        }

        $age = max(0, now()->timestamp - (int) $at);

        return ['state' => $age <= $ok ? 'ok' : ($age <= $warn ? 'stale' : 'never'), 'seconds' => $age];
    }

    private function count(string $table): ?int
    {
        try {
            return DB::table($table)->count();
        } catch (Throwable) {
            return null;
        }
    }

    private function database(): string
    {
        try {
            $connection = DB::connection();
            $version = $connection->selectOne($connection->getDriverName() === 'sqlite' ? 'select sqlite_version() as v' : 'select version() as v')->v ?? '?';

            return $connection->getDriverName().' '.$version;
        } catch (Throwable) {
            return 'unavailable';
        }
    }

    private function bytes(float|int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;

        return round($bytes / (1024 ** $i), 1).' '.$units[min($i, 4)];
    }
}
