<?php

namespace App\Modules\Messaging\Providers;

/**
 * WhatsApp Cloud API (Meta). Plain text messages only reach people who wrote to the business in the last 24 hours;
 * for anything else set an approved template name and its messages are sent as that template with the text as its one parameter.
 */
class WhatsAppCloudProvider extends BaseProvider
{
    public function code(): string
    {
        return 'whatsapp_cloud';
    }

    public function name(): string
    {
        return 'WhatsApp Cloud API';
    }

    public function channels(): array
    {
        return ['whatsapp'];
    }

    public function fields(): array
    {
        return [
            ['key' => 'phone_number_id', 'label' => 'Phone number ID', 'type' => 'text'],
            ['key' => 'access_token', 'label' => 'Permanent access token', 'type' => 'secret'],
            ['key' => 'template', 'label' => 'Approved template name (one text parameter)', 'type' => 'text', 'optional' => true],
            ['key' => 'template_language', 'label' => 'Template language code (e.g. en_US)', 'type' => 'text', 'optional' => true],
        ];
    }

    public function send(string $channel, string $to, string $text, array $config): void
    {
        $message = ['messaging_product' => 'whatsapp', 'to' => $this->digits($to)];
        $message += ! empty($config['template'])
            ? ['type' => 'template', 'template' => ['name' => $config['template'], 'language' => ['code' => $config['template_language'] ?: 'en_US'], 'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => mb_substr($text, 0, 1000)]]]]]]
            : ['type' => 'text', 'text' => ['body' => $text]];

        $this->check($this->http()->withToken((string) $config['access_token'])
            ->post('https://graph.facebook.com/v20.0/'.rawurlencode((string) $config['phone_number_id']).'/messages', $message), 'send');
    }
}
