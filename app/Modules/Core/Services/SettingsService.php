<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Key/value settings. restaurant_id null = platform settings (set by the super admin).
 * Secrets (API keys, passwords) are stored encrypted when $encrypt is true.
 */
class SettingsService
{
    private const CACHE_KEY = 'settings.all.%s';

    /** Settings read during this request, per scope: a page asks for dozens of values but should load them once. @var array<string, array<string, mixed>> */
    private array $loaded = [];

    private bool $tableKnown = false;

    public function get(string $key, mixed $default = null, ?int $restaurantId = null): mixed
    {
        $all = $this->all($restaurantId);

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function set(string $key, mixed $value, ?int $restaurantId = null, bool $encrypt = false): void
    {
        $stored = $value === null ? null : ($encrypt ? Crypt::encryptString((string) $value) : (string) $value);

        Setting::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'key' => $key],
            ['value' => $stored, 'is_encrypted' => $encrypt],
        );

        $this->flush($restaurantId);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(?int $restaurantId = null): array
    {
        // Before installation there may be no database at all (a missing sqlite file, wrong credentials),
        // and the installer pages must still open. A real database error after installation is not hidden.
        if (! $this->databaseReady()) {
            return [];
        }

        $scope = (string) ($restaurantId ?? 'platform');

        return $this->loaded[$scope] ??= Cache::remember(sprintf(self::CACHE_KEY, $scope), 3600, function () use ($restaurantId) {
            return Setting::query()
                ->where('restaurant_id', $restaurantId)
                ->get()
                ->mapWithKeys(fn (Setting $s) => [
                    $s->key => $s->is_encrypted && $s->value !== null ? Crypt::decryptString($s->value) : $s->value,
                ])
                ->all();
        });
    }

    private function databaseReady(): bool
    {
        $installed = config('installer.force_installed') || is_file((string) config('installer.lock_file'));

        if ($this->tableKnown) {
            return true;
        }

        try {
            return $this->tableKnown = Schema::hasTable('settings');
        } catch (QueryException $e) {
            if ($installed) {
                throw $e;
            }

            return false;
        }
    }

    public function flush(?int $restaurantId = null): void
    {
        unset($this->loaded[(string) ($restaurantId ?? 'platform')]);
        Cache::forget(sprintf(self::CACHE_KEY, $restaurantId ?? 'platform'));
    }
}
