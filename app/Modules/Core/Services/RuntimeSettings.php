<?php

namespace App\Modules\Core\Services;

/**
 * Pushes admin-managed settings (site name, timezone, SMTP, domains, realtime, S3) into Laravel's
 * config at the start of each request, so the panel can change them without touching .env.
 * Values left empty in the panel fall back to whatever .env provides.
 */
class RuntimeSettings
{
    public function __construct(private readonly SettingsService $settings) {}

    public function apply(): void
    {
        $s = $this->settings->all(null);

        $get = fn (string $key) => ($s[$key] ?? '') === '' ? null : $s[$key];

        if ($name = $get('site.name')) {
            config(['app.name' => $name, 'mail.from.name' => config('mail.from.name') === 'Laravel' ? $name : config('mail.from.name')]);
        }

        if ($tz = $get('general.timezone')) {
            config(['app.timezone' => $tz]);
            date_default_timezone_set($tz);
        }

        if ($lang = $get('general.default_language')) {
            config(['app.locale' => $lang, 'app.default_locale' => $lang]);
        }

        $this->mail($get);
        $this->domains($get);
        $this->realtime($get);
        $this->storage($get);
    }

    private function mail(callable $get): void
    {
        if ($host = $get('mail.host')) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => (int) ($get('mail.port') ?? 587),
                'mail.mailers.smtp.username' => $get('mail.username'),
                'mail.mailers.smtp.password' => $get('mail.password'),
                'mail.mailers.smtp.scheme' => match ($get('mail.encryption')) {
                    'ssl' => 'smtps',
                    default => 'smtp',
                },
            ]);
        }

        if ($from = $get('mail.from_address')) {
            config(['mail.from.address' => $from]);
        }

        if ($fromName = $get('mail.from_name')) {
            config(['mail.from.name' => $fromName]);
        }
    }

    private function domains(callable $get): void
    {
        $settings = $this->settings->all(null);

        if (array_key_exists('domains.subdomains_enabled', $settings)) {
            config(['tenancy.subdomains_enabled' => $settings['domains.subdomains_enabled'] === '1']);
        }

        if (array_key_exists('domains.custom_domains_enabled', $settings)) {
            config(['tenancy.custom_domains_enabled' => $settings['domains.custom_domains_enabled'] === '1']);
        }

        if ($base = $get('domains.base_domain')) {
            config(['tenancy.base_domain' => strtolower($base)]);
        }
    }

    private function realtime(callable $get): void
    {
        $driver = $get('realtime.driver') ?? 'polling';

        // Polling needs no broadcaster at all.
        config(['broadcasting.default' => $driver === 'polling' ? 'null' : $driver]);

        if ($driver === 'pusher') {
            config(['broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => $get('realtime.key'),
                'secret' => $get('realtime.secret'),
                'app_id' => $get('realtime.app_id'),
                'options' => ['cluster' => $get('realtime.cluster'), 'useTLS' => true],
            ]]);
        }

        if ($driver === 'reverb') {
            config(['broadcasting.connections.reverb' => [
                'driver' => 'reverb',
                'key' => $get('realtime.key'),
                'secret' => $get('realtime.secret'),
                'app_id' => $get('realtime.app_id'),
                'options' => ['host' => $get('realtime.host'), 'port' => (int) ($get('realtime.port') ?? 443), 'scheme' => $get('realtime.scheme') ?? 'https', 'useTLS' => ($get('realtime.scheme') ?? 'https') === 'https'],
            ]]);
        }
    }

    private function storage(callable $get): void
    {
        if ($get('storage.s3_bucket')) {
            config(['filesystems.disks.s3' => array_merge(config('filesystems.disks.s3'), array_filter([
                'key' => $get('storage.s3_key'),
                'secret' => $get('storage.s3_secret'),
                'region' => $get('storage.s3_region'),
                'bucket' => $get('storage.s3_bucket'),
                'endpoint' => $get('storage.s3_endpoint'),
                'url' => $get('storage.s3_url'),
                'use_path_style_endpoint' => (bool) $get('storage.s3_endpoint'),
            ]))]);
        }
    }
}
