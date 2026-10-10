<?php

namespace App\Modules\Admin\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;

/**
 * SQL dumps made in PHP, so backups work on shared hosting without mysqldump. MySQL/MariaDB and SQLite
 * are supported. Dumps live in storage/app/backups, which is not web-accessible.
 */
class DatabaseBackup
{
    private const NAME = '/^backup-\d{8}-\d{6}\.sql$/';

    private const CHUNK = 500;

    public function directory(): string
    {
        return storage_path('app/backups');
    }

    /**
     * @return list<array{name: string, size: int, created: int}> newest first
     */
    public function all(): array
    {
        return collect(File::glob($this->directory().'/backup-*.sql'))
            ->filter(fn ($p) => preg_match(self::NAME, basename($p)))
            ->map(fn ($p) => ['name' => basename($p), 'size' => filesize($p), 'created' => filemtime($p)])
            ->sortByDesc('created')->values()->all();
    }

    /** Absolute path of an existing backup, or null: names are validated, never concatenated blindly. */
    public function path(string $name): ?string
    {
        return preg_match(self::NAME, $name) && is_file($this->directory().'/'.$name) ? $this->directory().'/'.$name : null;
    }

    public function create(): string
    {
        File::ensureDirectoryExists($this->directory());
        $name = 'backup-'.now()->format('Ymd-His').'.sql';
        $path = $this->directory().'/'.$name;

        $handle = fopen($path, 'wb') ?: throw new RuntimeException('Cannot write backup file.');
        $connection = DB::connection();
        $pdo = $connection->getPdo();
        $driver = $connection->getDriverName();

        try {
            fwrite($handle, '-- Backup created '.now()->toIso8601String()." ({$driver})\n");
            fwrite($handle, $driver === 'mysql' ? "SET FOREIGN_KEY_CHECKS=0;\n\n" : "PRAGMA foreign_keys=OFF;\n\n");

            foreach ($this->tables($driver) as $table) {
                $this->dumpTable($handle, $pdo, $driver, $table);
            }

            fwrite($handle, $driver === 'mysql' ? "SET FOREIGN_KEY_CHECKS=1;\n" : "PRAGMA foreign_keys=ON;\n");
        } finally {
            fclose($handle);
        }

        return $name;
    }

    public function delete(string $name): bool
    {
        $path = $this->path($name);

        return $path !== null && File::delete($path);
    }

    /** Keep the newest $keep backups, remove the rest. Returns how many were removed. */
    public function prune(int $keep): int
    {
        $removed = 0;

        foreach (array_slice($this->all(), max(1, $keep)) as $old) {
            $removed += (int) $this->delete($old['name']);
        }

        return $removed;
    }

    /**
     * @return list<string>
     */
    private function tables(string $driver): array
    {
        return collect(DB::connection()->getSchemaBuilder()->getTables())
            ->pluck('name')
            ->reject(fn ($name) => str_starts_with($name, 'sqlite_'))
            ->values()->all();
    }

    private function dumpTable($handle, PDO $pdo, string $driver, string $table): void
    {
        $quoted = $driver === 'mysql' ? '`'.str_replace('`', '``', $table).'`' : '"'.str_replace('"', '""', $table).'"';

        fwrite($handle, "DROP TABLE IF EXISTS {$quoted};\n");

        if ($driver === 'mysql') {
            $create = $pdo->query("SHOW CREATE TABLE {$quoted}")->fetch(PDO::FETCH_NUM)[1];
        } else {
            $create = $pdo->query('select sql from sqlite_master where type = \'table\' and name = '.$pdo->quote($table))->fetchColumn();
        }

        fwrite($handle, $create.";\n");

        $statement = $pdo->query("SELECT * FROM {$quoted}", PDO::FETCH_NUM);
        $batch = [];

        while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
            $batch[] = '('.implode(',', array_map(fn ($v) => $this->literal($pdo, $v), $row)).')';

            if (count($batch) >= self::CHUNK) {
                fwrite($handle, "INSERT INTO {$quoted} VALUES\n".implode(",\n", $batch).";\n");
                $batch = [];
            }
        }

        if ($batch) {
            fwrite($handle, "INSERT INTO {$quoted} VALUES\n".implode(",\n", $batch).";\n");
        }

        fwrite($handle, "\n");
    }

    private function literal(PDO $pdo, mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_int($value) => (string) $value,
            is_float($value) => (string) $value,
            default => $pdo->quote((string) $value),
        };
    }
}
