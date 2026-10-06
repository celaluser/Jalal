<?php

namespace App\Modules\Admin\Settings;

use DateTimeZone;

/**
 * Declarative description of every platform setting screen. The admin SettingsController renders and
 * validates straight from this, so adding a setting means adding one line here.
 *
 * field keys: name (form field), key (settings key), type, label, rules, default, options, help.
 * types: text | email | url | number | textarea | select | toggle | secret | image
 */
final class SettingsSchema
{
    /**
     * @return array<string, array{title: string, description?: string, fields: list<array<string, mixed>>}>
     */
    public static function sections(): array
    {
        $sections = [
            'general' => [
                'title' => 'admin.settings.general.title',
                'description' => 'admin.settings.general.description',
                'fields' => [
                    ['name' => 'site_name', 'key' => 'site.name', 'type' => 'text', 'label' => 'admin.settings.general.site_name', 'default' => config('app.name'), 'rules' => ['required', 'string', 'max:100']],
                    ['name' => 'logo', 'key' => 'general.logo', 'type' => 'image', 'label' => 'admin.settings.general.logo'],
                    ['name' => 'favicon', 'key' => 'general.favicon', 'type' => 'image', 'label' => 'admin.settings.general.favicon'],
                    ['name' => 'default_language', 'key' => 'general.default_language', 'type' => 'select', 'label' => 'admin.settings.general.default_language', 'default' => config('app.locale'), 'options' => 'languages', 'rules' => ['required', 'string', 'max:12']],
                    ['name' => 'default_currency', 'key' => 'general.default_currency', 'type' => 'select', 'label' => 'admin.settings.general.default_currency', 'default' => 'USD', 'options' => 'currencies', 'rules' => ['required', 'string', 'max:8']],
                    ['name' => 'timezone', 'key' => 'general.timezone', 'type' => 'select', 'label' => 'admin.settings.general.timezone', 'default' => config('app.timezone'), 'options' => 'timezones', 'rules' => ['required', 'timezone:all']],
                    ['name' => 'support_email', 'key' => 'general.support_email', 'type' => 'email', 'label' => 'admin.settings.general.support_email', 'rules' => ['nullable', 'email', 'max:190']],
                    ['name' => 'maintenance', 'key' => 'general.maintenance', 'type' => 'toggle', 'label' => 'admin.settings.general.maintenance', 'help' => 'admin.settings.general.maintenance_help'],
                    ['name' => 'maintenance_message', 'key' => 'general.maintenance_message', 'type' => 'textarea', 'label' => 'admin.settings.general.maintenance_message', 'rules' => ['nullable', 'string', 'max:500']],
                ],
            ],
            'seo' => [
                'title' => 'admin.settings.seo.title',
                'fields' => [
                    ['name' => 'meta_title', 'key' => 'seo.meta_title', 'type' => 'text', 'label' => 'admin.settings.seo.meta_title', 'rules' => ['nullable', 'string', 'max:70']],
                    ['name' => 'meta_description', 'key' => 'seo.meta_description', 'type' => 'textarea', 'label' => 'admin.settings.seo.meta_description', 'rules' => ['nullable', 'string', 'max:300']],
                    ['name' => 'ga_id', 'key' => 'seo.google_analytics_id', 'type' => 'text', 'label' => 'admin.settings.seo.ga_id', 'help' => 'admin.settings.seo.ga_help', 'rules' => ['nullable', 'regex:/^(G|UA|GT)-[A-Za-z0-9-]{4,20}$/']],
                ],
            ],
            'security' => [
                'title' => 'admin.settings.security.title',
                'fields' => [
                    ['name' => 'recaptcha_enabled', 'key' => 'security.recaptcha_enabled', 'type' => 'toggle', 'label' => 'admin.settings.security.recaptcha_enabled', 'help' => 'admin.settings.security.recaptcha_help'],
                    ['name' => 'recaptcha_site_key', 'key' => 'security.recaptcha_site_key', 'type' => 'text', 'label' => 'admin.settings.security.recaptcha_site_key', 'rules' => ['nullable', 'string', 'max:200']],
                    ['name' => 'recaptcha_secret', 'key' => 'security.recaptcha_secret', 'type' => 'secret', 'label' => 'admin.settings.security.recaptcha_secret', 'rules' => ['nullable', 'string', 'max:200']],
                    ['name' => 'cookie_banner', 'key' => 'security.cookie_banner', 'type' => 'toggle', 'label' => 'admin.settings.security.cookie_banner', 'default' => '1'],
                    ['name' => 'cookie_text', 'key' => 'security.cookie_text', 'type' => 'textarea', 'label' => 'admin.settings.security.cookie_text', 'rules' => ['nullable', 'string', 'max:500']],
                ],
            ],
            'auth' => [
                'title' => 'admin.settings.auth.title',
                'fields' => [
                    ['name' => 'registration_enabled', 'key' => 'auth.registration_enabled', 'type' => 'toggle', 'label' => 'admin.settings.auth.registration_enabled', 'default' => '1'],
                    ['name' => 'google_enabled', 'key' => 'auth.google_enabled', 'type' => 'toggle', 'label' => 'admin.settings.auth.google_enabled'],
                    ['name' => 'google_client_id', 'key' => 'auth.google_client_id', 'type' => 'text', 'label' => 'admin.settings.auth.google_client_id', 'rules' => ['nullable', 'string', 'max:300']],
                    ['name' => 'google_client_secret', 'key' => 'auth.google_client_secret', 'type' => 'secret', 'label' => 'admin.settings.auth.google_client_secret', 'rules' => ['nullable', 'string', 'max:300']],
                ],
            ],
            'mail' => [
                'title' => 'admin.settings.mail.title',
                'description' => 'admin.settings.mail.description',
                'fields' => [
                    ['name' => 'host', 'key' => 'mail.host', 'type' => 'text', 'label' => 'admin.settings.mail.host', 'rules' => ['nullable', 'string', 'max:190']],
                    ['name' => 'port', 'key' => 'mail.port', 'type' => 'number', 'label' => 'admin.settings.mail.port', 'rules' => ['nullable', 'integer', 'between:1,65535']],
                    ['name' => 'encryption', 'key' => 'mail.encryption', 'type' => 'select', 'label' => 'admin.settings.mail.encryption', 'options' => ['' => 'None', 'tls' => 'TLS (STARTTLS)', 'ssl' => 'SSL'], 'rules' => ['nullable', 'in:,tls,ssl']],
                    ['name' => 'username', 'key' => 'mail.username', 'type' => 'text', 'label' => 'admin.settings.mail.username', 'rules' => ['nullable', 'string', 'max:190']],
                    ['name' => 'password', 'key' => 'mail.password', 'type' => 'secret', 'label' => 'admin.settings.mail.password', 'rules' => ['nullable', 'string', 'max:190']],
                    ['name' => 'from_address', 'key' => 'mail.from_address', 'type' => 'email', 'label' => 'admin.settings.mail.from_address', 'rules' => ['nullable', 'email', 'max:190']],
                    ['name' => 'from_name', 'key' => 'mail.from_name', 'type' => 'text', 'label' => 'admin.settings.mail.from_name', 'rules' => ['nullable', 'string', 'max:100']],
                ],
            ],
            'domains' => [
                'title' => 'admin.settings.domains.title',
                'description' => 'admin.settings.domains.description',
                'fields' => [
                    ['name' => 'subdomains_enabled', 'key' => 'domains.subdomains_enabled', 'type' => 'toggle', 'label' => 'admin.settings.domains.subdomains_enabled', 'help' => 'admin.settings.domains.subdomains_help'],
                    ['name' => 'base_domain', 'key' => 'domains.base_domain', 'type' => 'text', 'label' => 'admin.settings.domains.base_domain', 'rules' => ['nullable', 'regex:/^(?=.{1,190}$)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i']],
                    ['name' => 'custom_domains_enabled', 'key' => 'domains.custom_domains_enabled', 'type' => 'toggle', 'label' => 'admin.settings.domains.custom_domains_enabled'],
                    ['name' => 'dns_instructions', 'key' => 'domains.dns_instructions', 'type' => 'textarea', 'label' => 'admin.settings.domains.dns_instructions', 'help' => 'admin.settings.domains.dns_help', 'rules' => ['nullable', 'string', 'max:2000']],
                ],
            ],
            'ai' => [
                'title' => 'admin.settings.ai.title',
                'description' => 'admin.settings.ai.description',
                'fields' => [
                    ['name' => 'provider', 'key' => 'ai.provider', 'type' => 'select', 'label' => 'admin.settings.ai.provider', 'options' => ['' => 'Disabled', 'openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'gemini' => 'Google Gemini'], 'rules' => ['nullable', 'in:,openai,anthropic,gemini']],
                    ['name' => 'openai_key', 'key' => 'ai.openai_key', 'type' => 'secret', 'label' => 'admin.settings.ai.openai_key', 'rules' => ['nullable', 'string', 'max:300']],
                    ['name' => 'openai_model', 'key' => 'ai.openai_model', 'type' => 'text', 'label' => 'admin.settings.ai.openai_model', 'rules' => ['nullable', 'string', 'max:100']],
                    ['name' => 'anthropic_key', 'key' => 'ai.anthropic_key', 'type' => 'secret', 'label' => 'admin.settings.ai.anthropic_key', 'rules' => ['nullable', 'string', 'max:300']],
                    ['name' => 'anthropic_model', 'key' => 'ai.anthropic_model', 'type' => 'text', 'label' => 'admin.settings.ai.anthropic_model', 'rules' => ['nullable', 'string', 'max:100']],
                    ['name' => 'gemini_key', 'key' => 'ai.gemini_key', 'type' => 'secret', 'label' => 'admin.settings.ai.gemini_key', 'rules' => ['nullable', 'string', 'max:300']],
                    ['name' => 'gemini_model', 'key' => 'ai.gemini_model', 'type' => 'text', 'label' => 'admin.settings.ai.gemini_model', 'rules' => ['nullable', 'string', 'max:100']],
                    ...array_map(fn (string $task, int $default) => [
                        'name' => 'cost_'.$task, 'key' => 'ai.cost.'.$task, 'type' => 'number', 'label' => 'admin.settings.ai.cost_'.$task, 'default' => (string) $default, 'rules' => ['nullable', 'integer', 'min:0', 'max:100000'],
                    ], ['menu_import', 'description', 'translation', 'allergens', 'review_reply', 'insights'], [10, 1, 2, 1, 1, 5]),
                ],
            ],
            'realtime' => [
                'title' => 'admin.settings.realtime.title',
                'description' => 'admin.settings.realtime.description',
                'fields' => [
                    ['name' => 'driver', 'key' => 'realtime.driver', 'type' => 'select', 'label' => 'admin.settings.realtime.driver', 'options' => ['polling' => 'Polling (works everywhere)', 'pusher' => 'Pusher', 'reverb' => 'Laravel Reverb (needs a long-running process)'], 'default' => 'polling', 'rules' => ['required', 'in:polling,pusher,reverb']],
                    ['name' => 'polling_seconds', 'key' => 'realtime.polling_seconds', 'type' => 'number', 'label' => 'admin.settings.realtime.polling_seconds', 'default' => '5', 'rules' => ['nullable', 'integer', 'between:2,60']],
                    ['name' => 'app_id', 'key' => 'realtime.app_id', 'type' => 'text', 'label' => 'admin.settings.realtime.app_id', 'rules' => ['nullable', 'string', 'max:100']],
                    ['name' => 'key', 'key' => 'realtime.key', 'type' => 'text', 'label' => 'admin.settings.realtime.key', 'rules' => ['nullable', 'string', 'max:200']],
                    ['name' => 'secret', 'key' => 'realtime.secret', 'type' => 'secret', 'label' => 'admin.settings.realtime.secret', 'rules' => ['nullable', 'string', 'max:200']],
                    ['name' => 'cluster', 'key' => 'realtime.cluster', 'type' => 'text', 'label' => 'admin.settings.realtime.cluster', 'rules' => ['nullable', 'string', 'max:50']],
                    ['name' => 'host', 'key' => 'realtime.host', 'type' => 'text', 'label' => 'admin.settings.realtime.host', 'rules' => ['nullable', 'string', 'max:190']],
                    ['name' => 'port', 'key' => 'realtime.port', 'type' => 'number', 'label' => 'admin.settings.realtime.port', 'rules' => ['nullable', 'integer', 'between:1,65535']],
                    ['name' => 'scheme', 'key' => 'realtime.scheme', 'type' => 'select', 'label' => 'admin.settings.realtime.scheme', 'options' => ['https' => 'https', 'http' => 'http'], 'rules' => ['nullable', 'in:http,https']],
                ],
            ],
            'storage' => [
                'title' => 'admin.settings.storage.title',
                'fields' => [
                    ['name' => 'disk', 'key' => 'storage.disk', 'type' => 'select', 'label' => 'admin.settings.storage.disk', 'options' => ['public' => 'Local disk', 's3' => 'S3-compatible'], 'default' => 'public', 'rules' => ['required', 'in:public,s3']],
                    ['name' => 'image_quality', 'key' => 'storage.image_quality', 'type' => 'number', 'label' => 'admin.settings.storage.image_quality', 'default' => '82', 'rules' => ['nullable', 'integer', 'between:30,100']],
                    ['name' => 's3_key', 'key' => 'storage.s3_key', 'type' => 'text', 'label' => 'admin.settings.storage.s3_key', 'rules' => ['nullable', 'string', 'max:200']],
                    ['name' => 's3_secret', 'key' => 'storage.s3_secret', 'type' => 'secret', 'label' => 'admin.settings.storage.s3_secret', 'rules' => ['nullable', 'string', 'max:200']],
                    ['name' => 's3_region', 'key' => 'storage.s3_region', 'type' => 'text', 'label' => 'admin.settings.storage.s3_region', 'rules' => ['nullable', 'string', 'max:50']],
                    ['name' => 's3_bucket', 'key' => 'storage.s3_bucket', 'type' => 'text', 'label' => 'admin.settings.storage.s3_bucket', 'rules' => ['nullable', 'string', 'max:200']],
                    ['name' => 's3_endpoint', 'key' => 'storage.s3_endpoint', 'type' => 'url', 'label' => 'admin.settings.storage.s3_endpoint', 'rules' => ['nullable', 'url', 'max:300']],
                    ['name' => 's3_url', 'key' => 'storage.s3_url', 'type' => 'url', 'label' => 'admin.settings.storage.s3_url', 'rules' => ['nullable', 'url', 'max:300']],
                ],
            ],
        ];

        return $sections;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function section(string $name): ?array
    {
        return self::sections()[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function timezones(): array
    {
        $zones = DateTimeZone::listIdentifiers();

        return array_combine($zones, $zones);
    }
}
