<?php

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Payments\CheckoutResult;
use App\Modules\Billing\Payments\GatewayConfig;
use App\Modules\Billing\Payments\PaymentNotification;
use Illuminate\Http\Request;

/**
 * A subscription payment gateway. To add one: implement this, then register it in
 * GatewayManager (or from an add-on: app(GatewayManager::class)->register(new MyGateway)).
 *
 * Contract for implementers:
 *  - never trust the browser: webhook() must verify a signature or re-fetch the payment from the
 *    gateway's API before returning a "succeeded" notification;
 *  - the merchant reference sent to the gateway is always the invoice number;
 *  - never put secrets in exception messages.
 */
interface PaymentGatewayInterface
{
    /** Stable identifier used in URLs and settings keys, e.g. "stripe". */
    public function code(): string;

    public function name(): string;

    /**
     * Settings the super admin must fill in. type: text | secret | textarea | select (with options).
     * Fields are required unless marked optional.
     *
     * @return list<array{key: string, label: string, type: string, options?: array<string, string>, optional?: bool}>
     */
    public function fields(): array;

    public function supportsCurrency(string $currency): bool;

    /** Start a payment for an open invoice. */
    public function checkout(Invoice $invoice, GatewayConfig $config, string $returnUrl, string $cancelUrl, string $webhookUrl): CheckoutResult;

    /**
     * Handle the gateway's server-to-server callback. Return null for events to ignore.
     *
     * @throws GatewayException when the request is not authentic
     */
    public function webhook(Request $request, GatewayConfig $config): ?PaymentNotification;

    /**
     * Handle the customer returning from the hosted page. Gateways that need a capture/verify call do it
     * here. Return null when the payment is not (yet) confirmed.
     */
    public function confirmReturn(Request $request, Invoice $invoice, GatewayConfig $config): ?PaymentNotification;
}
