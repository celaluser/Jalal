<?php

namespace App\Modules\Billing\Payments;

final class CheckoutResult
{
    public function __construct(
        /** Where to send the customer, or null for offline methods. */
        public readonly ?string $redirectUrl,
        /** The gateway's id for this attempt (session / order / payment id). */
        public readonly string $reference,
        /** Text shown instead of a redirect (bank transfer details). */
        public readonly ?string $instructions = null,
    ) {}
}
