<?php

namespace App\Modules\Messaging\Providers;

use App\Modules\Messaging\Exceptions\MessagingException;

/** Twilio: SMS from a number or messaging service, and WhatsApp through the same account. */
class TwilioProvider extends BaseProvider
{
    public function code(): string
    {
        return 'twilio';
    }

    public function name(): string
    {
        return 'Twilio';
    }

    public function channels(): array
    {
        return ['sms', 'whatsapp'];
    }

    public function fields(): array
    {
        return [
            ['key' => 'account_sid', 'label' => 'Account SID', 'type' => 'text'],
            ['key' => 'auth_token', 'label' => 'Auth token', 'type' => 'secret'],
            ['key' => 'from', 'label' => 'SMS sender (number or Messaging Service SID MG...)', 'type' => 'text', 'optional' => true],
            ['key' => 'whatsapp_from', 'label' => 'WhatsApp sender (number, with country code)', 'type' => 'text', 'optional' => true],
        ];
    }

    public function send(string $channel, string $to, string $text, array $config): void
    {
        $whatsapp = $channel === 'whatsapp';
        $from = (string) ($whatsapp ? ($config['whatsapp_from'] ?? '') : ($config['from'] ?? ''));

        if ($from === '') {
            throw new MessagingException('Twilio: no sender is set for '.$channel.'.');
        }

        $prefix = $whatsapp ? 'whatsapp:' : '';
        $body = ['To' => $prefix.'+'.$this->digits($to), 'Body' => $text];
        $body += str_starts_with($from, 'MG') ? ['MessagingServiceSid' => $from] : ['From' => $prefix.'+'.$this->digits($from)];

        $this->check($this->http()->asForm()->withBasicAuth((string) $config['account_sid'], (string) $config['auth_token'])
            ->post('https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode((string) $config['account_sid']).'/Messages.json', $body), 'send');
    }
}
