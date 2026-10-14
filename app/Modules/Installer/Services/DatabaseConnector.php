<?php

namespace App\Modules\Installer\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Builds a connection config from installer input, tests it and activates it for this request.
 */
class DatabaseConnector
{
    /**
     * @param  array<string, mixed>  $input  driver, host, port, database, username, password
     * @return array<string, mixed>
     */
    public function config(array $input): array
    {
        if (($input['driver'] ?? 'mysql') === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => $input['database'],
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }

        return [
            'driver' => 'mysql',
            'host' => $input['host'],
            'port' => $input['port'] ?: 3306,
            'database' => $input['database'],
            'username' => $input['username'],
            'password' => $input['password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ];
    }

    /**
     * @return string|null error message, or null when the connection works
     */
    public function test(array $input): ?string
    {
        config(['database.connections.installer' => $this->config($input)]);
        DB::purge('installer');

        try {
            DB::connection('installer')->getPdo();

            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        } finally {
            DB::purge('installer');
        }
    }

    /**
     * Make the given database the default connection for the rest of this request.
     */
    public function activate(array $input): void
    {
        // A dedicated connection name keeps the app's own configured connections untouched.
        config(['database.connections.installer_target' => $this->config($input), 'database.default' => 'installer_target']);
        DB::purge('installer_target');
    }

    public function isEmpty(): bool
    {
        return count(DB::connection()->getSchemaBuilder()->getTables()) === 0;
    }
}
