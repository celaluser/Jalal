<?php

namespace App\Modules\Messaging\Contracts;

use App\Modules\Messaging\Exceptions\MessagingException;

/**
 * An SMS or WhatsApp sending service. To add one: implement this and register it with
 * MessagingManager (add-ons do it from their service provider).
 */
interface MessageProvider
{
    /** Stable id used in settings keys, e.g. "twilio". */
    public function code(): string;

    public function name(): string;

    /** @return list<string> any of "sms", "whatsapp" */
    public function channels(): array;

    /**
     * Settings the super admin fills in. type: text | secret | select (with options).
     *
     * @return list<array{key: string, label: string, type: string, options?: array<string, string>, optional?: bool}>
     */
    public function fields(): array;

    /**
     * @param  string  $to  international number, digits with a leading +
     * @param  array<string, string|null>  $config
     *
     * @throws MessagingException
     */
    public function send(string $channel, string $to, string $text, array $config): void;
}
