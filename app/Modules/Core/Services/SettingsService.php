<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Setting;
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
        if (! Schema::hasTable('settings')) {
            return [];
        }

        return Cache::remember(sprintf(self::CACHE_KEY, $restaurantId ?? 'platform'), 3600, function () use ($restaurantId) {
            return Setting::query()
                ->where('restaurant_id', $restaurantId)
                ->get()
                ->mapWithKeys(fn (Setting $s) => [
                    $s->key => $s->is_encrypted && $s->value !== null ? Crypt::decryptString($s->value) : $s->value,
                ])
                ->all();
        });
    }

    public function flush(?int $restaurantId = null): void
    {
        Cache::forget(sprintf(self::CACHE_KEY, $restaurantId ?? 'platform'));
    }
}
