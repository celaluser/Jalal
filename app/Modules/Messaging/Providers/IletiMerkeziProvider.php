<?php

namespace App\Modules\Messaging\Providers;

use App\Modules\Messaging\Exceptions\MessagingException;

/** İleti Merkezi (Turkey). Requests carry the public key and an HMAC-SHA256 of it made with the secret key. */
class IletiMerkeziProvider extends BaseProvider
{
    public function code(): string
    {
        return 'iletimerkezi';
    }

    public function name(): string
    {
        return 'İleti Merkezi (Turkey)';
    }

    public function channels(): array
    {
        return ['sms'];
    }

    public function fields(): array
    {
        return [
            ['key' => 'public_key', 'label' => 'Public key', 'type' => 'text'],
            ['key' => 'secret_key', 'label' => 'Secret key', 'type' => 'secret'],
            ['key' => 'sender', 'label' => 'Approved sender title', 'type' => 'text'],
        ];
    }

    public function send(string $channel, string $to, string $text, array $config): void
    {
        $key = (string) $config['public_key'];

        $response = $this->http()->post('https://api.iletimerkezi.com/v1/send-sms/json', ['request' => [
            'authentication' => ['key' => $key, 'hash' => hash_hmac('sha256', $key, (string) $config['secret_key'])],
            'order' => [
                'sender' => $config['sender'],
                'message' => ['text' => $text, 'receipents' => ['number' => [$this->digits($to)]]],
            ],
        ]]);

        $this->check($response, 'send');

        $code = (int) $response->json('response.status.code');

        if ($code !== 200) {
            throw new MessagingException('İleti Merkezi: send failed ('.mb_substr((string) $response->json('response.status.message'), 0, 120).').');
        }
    }
}
