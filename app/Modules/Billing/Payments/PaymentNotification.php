<?php

namespace App\Modules\Billing\Payments;

/**
 * What a gateway tells us about one payment, normalised. amount is in minor units.
 */
final class PaymentNotification
{
    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    /**
     * @param  array<string, mixed>  $payload  kept for audit; must not contain secrets
     */
    public function __construct(
        public readonly string $gateway,
        public readonly string $invoiceNumber,
        public readonly string $transactionId,
        public readonly string $status,
        public readonly int $amount,
        public readonly string $currency,
        public readonly array $payload = [],
    ) {}
}
