<?php

namespace App\Modules\Admin\Services;

use Illuminate\Support\Facades\File;

/**
 * Reads Laravel log files for the admin log viewer. Only files inside storage/logs are reachable and
 * only by their listed name, and only the tail is read so a huge log cannot exhaust memory.
 */
class LogReader
{
    /** How much of the end of the file is parsed. */
    private const TAIL_BYTES = 1_500_000;

    public const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    /**
     * @return list<array{name: string, size: int, modified: int}> newest first
     */
    public function files(): array
    {
        return collect(File::glob(storage_path('logs/*.log')))
            ->map(fn (string $path) => ['name' => basename($path), 'size' => filesize($path), 'modified' => filemtime($path)])
            ->sortByDesc('modified')->values()->all();
    }

    /** The absolute path of a listed log file, or null when the name is not one of them. */
    public function path(string $name): ?string
    {
        return collect($this->files())->contains('name', $name) ? storage_path('logs/'.$name) : null;
    }

    /**
     * @return list<array{time: string, env: string, level: string, message: string, trace: string}> newest first
     */
    public function entries(string $name, ?string $level = null, ?string $search = null, int $limit = 200): array
    {
        $path = $this->path($name);

        if ($path === null || ! is_readable($path)) {
            return [];
        }

        $size = filesize($path);
        $handle = fopen($path, 'rb');

        if ($size > self::TAIL_BYTES) {
            fseek($handle, -self::TAIL_BYTES, SEEK_END);
            fgets($handle); // drop the partial first line
        }

        $entries = [];
        $current = null;

        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}[^\]]*)\] (\w+)\.(\w+): (.*)$/', $line, $m)) {
                if ($current) {
                    $entries[] = $current;
                }
                $current = ['time' => $m[1], 'env' => $m[2], 'level' => strtolower($m[3]), 'message' => rtrim($m[4]), 'trace' => ''];
            } elseif ($current) {
                $current['trace'] .= $line;
            }
        }

        if ($current) {
            $entries[] = $current;
        }

        fclose($handle);

        return collect($entries)
            ->when($level && in_array($level, self::LEVELS, true), fn ($c) => $c->where('level', $level))
            ->when($search !== null && $search !== '', fn ($c) => $c->filter(fn ($e) => stripos($e['message'].$e['trace'], $search) !== false))
            ->reverse()->take($limit)->values()->all();
    }

    public function clear(string $name): bool
    {
        $path = $this->path($name);

        return $path !== null && file_put_contents($path, '') !== false;
    }
}
