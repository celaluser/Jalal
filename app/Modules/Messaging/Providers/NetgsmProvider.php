<?php

namespace App\Modules\Messaging\Providers;

use App\Modules\Messaging\Exceptions\MessagingException;

/** Netgsm (Turkey), REST v2. Success is code "00" in the JSON body even with HTTP 200. */
class NetgsmProvider extends BaseProvider
{
    public function code(): string
    {
        return 'netgsm';
    }

    public function name(): string
    {
        return 'Netgsm (Turkey)';
    }

    public function channels(): array
    {
        return ['sms'];
    }

    public function fields(): array
    {
        return [
            ['key' => 'usercode', 'label' => 'User code (subscriber number)', 'type' => 'text'],
            ['key' => 'password', 'label' => 'API password', 'type' => 'secret'],
            ['key' => 'header', 'label' => 'Approved sender title (msgheader)', 'type' => 'text'],
        ];
    }

    public function send(string $channel, string $to, string $text, array $config): void
    {
        $response = $this->http()->withBasicAuth((string) $config['usercode'], (string) $config['password'])
            ->post('https://api.netgsm.com.tr/sms/rest/v2/send', [
                'msgheader' => $config['header'],
                'messages' => [['msg' => $text, 'no' => $this->digits($to)]],
                'encoding' => 'TR',
            ]);

        $this->check($response, 'send');

        if (! in_array((string) $response->json('code'), ['00', '01', '02'], true)) {
            throw new MessagingException('Netgsm: send failed (code '.$response->json('code').').');
        }
    }
}
