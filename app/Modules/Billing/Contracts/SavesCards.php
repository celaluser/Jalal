<?php

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\SavedCard;
use App\Modules\Billing\Payments\GatewayConfig;
use App\Modules\Billing\Payments\PaymentNotification;

/** A gateway that can keep the customer's card after a payment and charge it later without the customer present. */
interface SavesCards
{
    /**
     * What the gateway knows about the card used for a payment, or null when none was kept.
     *
     * @return array{customer: string, method: string, brand: ?string, last4: ?string, exp_month: ?int, exp_year: ?int}|null
     */
    public function cardFromPayment(string $paymentReference, GatewayConfig $config): ?array;

    /** Charges the kept card for an open invoice. The result says whether it worked; a refusal is a FAILED notification, not an exception. */
    public function chargeSaved(Invoice $invoice, SavedCard $card, GatewayConfig $config): PaymentNotification;
}
