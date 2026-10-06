<?php

namespace App\Modules\Installer\Services;

class RequirementsChecker
{
    /**
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    public function checks(): array
    {
        $checks = [[
            'label' => 'PHP >= '.config('installer.min_php'),
            'ok' => version_compare(PHP_VERSION, config('installer.min_php'), '>='),
            'detail' => PHP_VERSION,
        ]];

        foreach (config('installer.extensions') as $extension) {
            $checks[] = [
                'label' => "PHP extension: {$extension}",
                'ok' => extension_loaded($extension),
                'detail' => extension_loaded($extension) ? 'OK' : 'Missing',
            ];
        }

        $checks[] = [
            'label' => 'WebP support (GD)',
            'ok' => function_exists('imagewebp'),
            'detail' => function_exists('imagewebp') ? 'OK' : 'GD without WebP',
        ];

        foreach (config('installer.writable') as $path) {
            $full = base_path($path);
            $ok = is_dir($full) && is_writable($full);
            $checks[] = ['label' => "Writable: {$path}", 'ok' => $ok, 'detail' => $ok ? 'OK' : 'Not writable'];
        }

        $envOk = is_file(base_path('.env')) ? is_writable(base_path('.env')) : is_writable(base_path());
        $checks[] = ['label' => 'Writable: .env', 'ok' => $envOk, 'detail' => $envOk ? 'OK' : 'Not writable'];

        return $checks;
    }

    public function passes(): bool
    {
        return collect($this->checks())->every(fn (array $check) => $check['ok']);
    }
}
