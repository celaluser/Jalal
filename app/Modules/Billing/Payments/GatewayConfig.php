<?php

namespace App\Modules\Billing\Payments;

/** Decrypted admin settings of one gateway. */
final class GatewayConfig
{
    /** @param array<string, string|null> $values */
    public function __construct(private readonly array $values) {}

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->values[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public function isLive(): bool
    {
        return $this->get('mode', 'test') === 'live';
    }
}
