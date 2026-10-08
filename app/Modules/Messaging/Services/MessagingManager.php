<?php

namespace App\Modules\Messaging\Services;

use App\Modules\Core\Services\SettingsService;
use App\Modules\Messaging\Contracts\MessageProvider;
use App\Modules\Messaging\Providers\MessageBirdProvider;
use App\Modules\Messaging\Providers\TwilioProvider;
use App\Modules\Messaging\Providers\VonageProvider;
use App\Modules\Messaging\Providers\WhatsAppCloudProvider;

/**
 * Registry of SMS/WhatsApp providers and the platform's choice of which one serves each channel.
 * Settings: messaging.sms_provider, messaging.whatsapp_provider, messaging.{code}.{field} (secrets encrypted).
 */
class MessagingManager
{
    /** @var array<string, MessageProvider> */
    private array $providers = [];

    public function __construct(private readonly SettingsService $settings)
    {
        foreach ([new TwilioProvider, new VonageProvider, new MessageBirdProvider, new WhatsAppCloudProvider] as $p) {
            $this->register($p);
        }
    }

    public function register(MessageProvider $provider): void
    {
        $this->providers[$provider->code()] = $provider;
    }

    /** @return array<string, MessageProvider> */
    public function all(): array
    {
        return $this->providers;
    }

    public function find(string $code): ?MessageProvider
    {
        return $this->providers[$code] ?? null;
    }

    /** The provider chosen for a channel, only when it supports it and every required field is filled in. */
    public function forChannel(string $channel): ?MessageProvider
    {
        $code = (string) $this->settings->get("messaging.{$channel}_provider", '');
        $provider = $this->find($code);

        return $provider && in_array($channel, $provider->channels(), true) && $this->isConfigured($provider, $channel) ? $provider : null;
    }

    public function available(string $channel): bool
    {
        return $this->forChannel($channel) !== null;
    }

    /** @return array<string, string|null> */
    public function config(MessageProvider $provider): array
    {
        $values = [];

        foreach ($provider->fields() as $field) {
            $values[$field['key']] = $this->settings->get("messaging.{$provider->code()}.{$field['key']}");
        }

        return $values;
    }

    public function isConfigured(MessageProvider $provider, ?string $channel = null): bool
    {
        $config = $this->config($provider);

        foreach ($provider->fields() as $field) {
            if (empty($field['optional']) && ($config[$field['key']] === null || $config[$field['key']] === '')) {
                return false;
            }
        }

        // Twilio keeps one sender per channel: asking it for WhatsApp needs the WhatsApp sender.
        if ($provider->code() === 'twilio') {
            return $channel === 'whatsapp' ? ! empty($config['whatsapp_from']) : ($channel === 'sms' ? ! empty($config['from']) : true);
        }

        return true;
    }

    /** @param array<string, string|null> $input blank secrets keep the stored value */
    public function save(MessageProvider $provider, array $input): void
    {
        foreach ($provider->fields() as $field) {
            if (! array_key_exists($field['key'], $input)) {
                continue;
            }

            $value = $input[$field['key']];

            if ($field['type'] === 'secret' && ($value === null || $value === '')) {
                continue;
            }

            $this->settings->set("messaging.{$provider->code()}.{$field['key']}", $value, encrypt: $field['type'] === 'secret');
        }
    }

    public function choose(string $channel, ?string $code): void
    {
        $this->settings->set("messaging.{$channel}_provider", $code ?: '');
    }
}
