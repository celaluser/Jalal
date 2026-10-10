<?php

namespace App\Modules\Installer\Services;

use RuntimeException;

/**
 * Reads and updates the project's .env file, keeping comments and unrelated lines untouched.
 */
class EnvWriter
{
    public function __construct(private readonly ?string $path = null) {}

    public function path(): string
    {
        return $this->path ?? base_path('.env');
    }

    /**
     * Create .env from .env.example when it does not exist yet.
     */
    public function ensureExists(): void
    {
        if (is_file($this->path())) {
            return;
        }

        $example = dirname($this->path()).'/.env.example';

        if (! is_file($example) || ! @copy($example, $this->path())) {
            throw new RuntimeException('Cannot create .env: the project folder is not writable.');
        }
    }

    /**
     * @param  array<string, string|int|bool|null>  $values
     */
    public function set(array $values): void
    {
        $this->ensureExists();

        $contents = (string) file_get_contents($this->path());

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->format($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $contents)
                : rtrim($contents, "\n")."\n".$line."\n";
        }

        if (file_put_contents($this->path(), $contents, LOCK_EX) === false) {
            throw new RuntimeException('Cannot write .env.');
        }
    }

    public function get(string $key): ?string
    {
        if (! is_file($this->path())) {
            return null;
        }

        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', (string) file_get_contents($this->path()), $m)) {
            return null;
        }

        return trim($m[1], " \t\"'");
    }

    private function format(string|int|bool|null $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        // Strip line breaks: a value must never be able to inject another variable.
        $value = str_replace(["\r", "\n"], '', $value);

        if ($value === '' || preg_match('/^[A-Za-z0-9_\-.:\/,@+=]+$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
