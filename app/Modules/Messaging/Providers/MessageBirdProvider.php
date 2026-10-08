<?php

namespace App\Modules\Messaging\Providers;

/** MessageBird (Bird) SMS. */
class MessageBirdProvider extends BaseProvider
{
    public function code(): string
    {
        return 'messagebird';
    }

    public function name(): string
    {
        return 'MessageBird';
    }

    public function channels(): array
    {
        return ['sms'];
    }

    public function fields(): array
    {
        return [
            ['key' => 'access_key', 'label' => 'Access key', 'type' => 'secret'],
            ['key' => 'originator', 'label' => 'Sender (originator)', 'type' => 'text'],
        ];
    }

    public function send(string $channel, string $to, string $text, array $config): void
    {
        $this->check($this->http()->withHeaders(['Authorization' => 'AccessKey '.$config['access_key']])->post('https://rest.messagebird.com/messages', [
            'originator' => $config['originator'], 'recipients' => [$this->digits($to)], 'body' => $text,
        ]), 'send');
    }
}
