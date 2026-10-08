<?php

namespace App\Modules\Messaging\Providers;

use App\Modules\Messaging\Exceptions\MessagingException;

/** Vonage SMS API. It answers 200 even for rejected messages, so the status inside the body decides. */
class VonageProvider extends BaseProvider
{
    public function code(): string
    {
        return 'vonage';
    }

    public function name(): string
    {
        return 'Vonage';
    }

    public function channels(): array
    {
        return ['sms'];
    }

    public function fields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API key', 'type' => 'text'],
            ['key' => 'api_secret', 'label' => 'API secret', 'type' => 'secret'],
            ['key' => 'from', 'label' => 'Sender ID or number', 'type' => 'text'],
        ];
    }

    public function send(string $channel, string $to, string $text, array $config): void
    {
        $response = $this->http()->asForm()->post('https://rest.nexmo.com/sms/json', [
            'api_key' => $config['api_key'], 'api_secret' => $config['api_secret'], 'from' => $config['from'],
            'to' => $this->digits($to), 'text' => $text, 'type' => 'unicode',
        ]);
        $this->check($response, 'send');

        $status = (string) $response->json('messages.0.status', '0');

        if ($status !== '0') {
            throw new MessagingException('Vonage: message rejected ('.$response->json('messages.0.error-text', 'status '.$status).').');
        }
    }
}
