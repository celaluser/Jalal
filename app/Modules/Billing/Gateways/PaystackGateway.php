<?php

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Payments\CheckoutResult;
use App\Modules\Billing\Payments\GatewayConfig;
use App\Modules\Billing\Payments\PaymentNotification;
use Illuminate\Http\Request;

/** Paystack. Webhook signed with HMAC-SHA512 of the body using the secret key. */
class PaystackGateway extends BaseGateway
{
    private const API = 'https://api.paystack.co';

    public function code(): string
    {
        return 'paystack';
    }

    public function name(): string
    {
        return 'Paystack';
    }

    public function fields(): array
    {
        return [['key' => 'secret_key', 'label' => 'Secret key (sk_...)', 'type' => 'secret']];
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), ['NGN', 'GHS', 'ZAR', 'KES', 'USD'], true);
    }

    public function checkout(Invoice $invoice, GatewayConfig $config, string $returnUrl, string $cancelUrl, string $webhookUrl): CheckoutResult
    {
        $email = $this->buyerEmail($invoice) ?? throw new GatewayException('Paystack: the restaurant has no owner e-mail address.');

        $data = $this->json($this->http()->withToken($config->get('secret_key'))->post(self::API.'/transaction/initialize', [
            'email' => $email,
            'amount' => $this->minor($invoice),
            'currency' => strtoupper($invoice->currency_code),
            'reference' => $invoice->number,
            'callback_url' => $returnUrl,
            'metadata' => ['invoice' => $invoice->number, 'cancel_action' => $cancelUrl],
        ]), 'checkout');

        return new CheckoutResult($data['data']['authorization_url'] ?? throw new GatewayException('Paystack: no payment URL returned.'), (string) $data['data']['reference']);
    }

    public function webhook(Request $request, GatewayConfig $config): ?PaymentNotification
    {
        $expected = hash_hmac('sha512', $request->getContent(), (string) $config->get('secret_key'));

        if (! $this->signatureMatches($expected, (string) $request->header('x-paystack-signature'))) {
            throw new GatewayException('Paystack: invalid webhook signature.');
        }

        $event = json_decode($request->getContent(), true);

        return ($event['event'] ?? null) === 'charge.success' ? $this->notification($event['data'] ?? []) : null;
    }

    public function confirmReturn(Request $request, Invoice $invoice, GatewayConfig $config): ?PaymentNotification
    {
        $data = $this->json($this->http()->withToken($config->get('secret_key'))->get(self::API.'/transaction/verify/'.rawurlencode($invoice->number)), 'verification');

        return ($data['data']['status'] ?? null) === 'success' ? $this->notification($data['data']) : null;
    }

    /** @param array<string, mixed> $tx */
    private function notification(array $tx): ?PaymentNotification
    {
        if (($tx['status'] ?? null) !== 'success') {
            return null;
        }

        return new PaymentNotification('paystack', (string) ($tx['reference'] ?? ''), (string) ($tx['id'] ?? $tx['reference']), PaymentNotification::SUCCEEDED,
            (int) ($tx['amount'] ?? 0), strtoupper((string) ($tx['currency'] ?? '')), ['reference' => $tx['reference'] ?? null]);
    }
}
