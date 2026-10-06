<?php

namespace App\Modules\Installer\Services;

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Licensing\Services\LicenseManager;
use App\Modules\Licensing\Support\LicenseResult;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class InstallerService
{
    public function __construct(
        private readonly EnvWriter $env,
        private readonly DatabaseConnector $database,
        private readonly LicenseManager $licenses,
    ) {}

    public function isInstalled(): bool
    {
        return config('installer.force_installed') || is_file(config('installer.lock_file'));
    }

    /**
     * @param  array{license: array{code: string, valid: bool}, database: array<string, mixed>, admin: array<string, string>, site: array<string, string>}  $data
     */
    public function install(array $data): void
    {
        $this->database->activate($data['database']);

        if (! $this->database->isEmpty()) {
            throw new RuntimeException(__('installer.database_not_empty'));
        }

        $this->writeEnvironment($data);

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);

        $admin = User::create([
            'name' => $data['admin']['name'],
            'email' => $data['admin']['email'],
            'password' => $data['admin']['password'],
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
        $admin->assignRole(Permissions::SUPER_ADMIN);

        $this->licenses->record(
            $data['license']['code'],
            $data['license']['valid'] ? LicenseResult::valid() : LicenseResult::unreachable('unverified')
        );

        app(SettingsService::class)->set('site.name', $data['site']['name']);

        $this->lock();
    }

    private function writeEnvironment(array $data): void
    {
        $db = $data['database'];

        $values = [
            'APP_NAME' => $data['site']['name'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => false,
            'APP_URL' => $data['site']['url'],
            'DB_CONNECTION' => $db['driver'] === 'sqlite' ? 'sqlite' : 'mysql',
            'DB_DATABASE' => $db['database'],
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
        ];

        if ($db['driver'] !== 'sqlite') {
            $values += [
                'DB_HOST' => $db['host'],
                'DB_PORT' => $db['port'] ?: 3306,
                'DB_USERNAME' => $db['username'],
                'DB_PASSWORD' => $db['password'] ?? '',
            ];
        }

        $this->env->set($values);
    }

    private function lock(): void
    {
        $file = config('installer.lock_file');
        File::ensureDirectoryExists(dirname($file));
        File::put($file, json_encode(['installed_at' => now()->toIso8601String(), 'version' => config('version.current')]));
    }
}
