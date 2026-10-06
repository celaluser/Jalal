<?php

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Payments\CheckoutResult;
use App\Modules\Billing\Payments\GatewayConfig;
use App\Modules\Billing\Payments\PaymentNotification;
use Illuminate\Http\Request;

/** Stripe Checkout (hosted page). Webhook: checkout.session.completed, signed with the endpoint secret. */
class StripeGateway extends BaseGateway
{
    private const API = 'https://api.stripe.com/v1';

    private const TOLERANCE_SECONDS = 300;

    public function code(): string
    {
        return 'stripe';
    }

    public function name(): string
    {
        return 'Stripe';
    }

    public function fields(): array
    {
        return [
            ['key' => 'secret_key', 'label' => 'Secret key (sk_...)', 'type' => 'secret'],
            ['key' => 'webhook_secret', 'label' => 'Webhook signing secret (whsec_...)', 'type' => 'secret'],
        ];
    }

    public function checkout(Invoice $invoice, GatewayConfig $config, string $returnUrl, string $cancelUrl, string $webhookUrl): CheckoutResult
    {
        $response = $this->http()->withToken($config->get('secret_key'))->asForm()->post(self::API.'/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $invoice->number,
            'customer_email' => $this->buyerEmail($invoice),
            'metadata' => ['invoice' => $invoice->number],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($invoice->currency_code),
                    'unit_amount' => $this->minor($invoice),
                    'product_data' => ['name' => $this->description($invoice)],
                ],
            ]],
        ]);

        $data = $this->json($response, 'checkout');

        return new CheckoutResult($data['url'] ?? throw new GatewayException('Stripe: no checkout URL returned.'), (string) $data['id']);
    }

    public function webhook(Request $request, GatewayConfig $config): ?PaymentNotification
    {
        $payload = $request->getContent();
        $this->assertSignature($payload, (string) $request->header('Stripe-Signature'), (string) $config->get('webhook_secret'));

        $event = json_decode($payload, true);
        $object = $event['data']['object'] ?? [];

        return match ($event['type'] ?? null) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => ($object['payment_status'] ?? null) === 'paid'
                ? $this->notification($object, PaymentNotification::SUCCEEDED) : null,
            'checkout.session.async_payment_failed' => $this->notification($object, PaymentNotification::FAILED),
            default => null,
        };
    }

    public function confirmReturn(Request $request, Invoice $invoice, GatewayConfig $config): ?PaymentNotification
    {
        $sessionId = (string) $request->query('session_id');

        if (! preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) {
            return null;
        }

        $session = $this->json($this->http()->withToken($config->get('secret_key'))->get(self::API.'/checkout/sessions/'.$sessionId), 'session lookup');

        if (($session['client_reference_id'] ?? null) !== $invoice->number || ($session['payment_status'] ?? null) !== 'paid') {
            return null;
        }

        return $this->notification($session, PaymentNotification::SUCCEEDED);
    }

    /**
     * Stripe-Signature: t=timestamp,v1=hex(hmac_sha256("t.payload", secret)). Replays outside the
     * tolerance window are rejected.
     */
    private function assertSignature(string $payload, string $header, string $secret): void
    {
        $parts = ['t' => null, 'v1' => []];

        foreach (explode(',', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            $key === 'v1' ? $parts['v1'][] = $value : $parts[$key] = $value;
        }

        $timestamp = (int) $parts['t'];
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        $valid = $secret !== '' && $timestamp > 0
            && abs(time() - $timestamp) <= self::TOLERANCE_SECONDS
            && collect($parts['v1'])->contains(fn ($sig) => hash_equals($expected, $sig));

        if (! $valid) {
            throw new GatewayException('Stripe: invalid webhook signature.');
        }
    }

    /** @param array<string, mixed> $session */
    private function notification(array $session, string $status): PaymentNotification
    {
        return new PaymentNotification(
            'stripe',
            (string) ($session['client_reference_id'] ?? ''),
            (string) ($session['payment_intent'] ?? $session['id'] ?? ''),
            $status,
            (int) ($session['amount_total'] ?? 0),
            strtoupper((string) ($session['currency'] ?? '')),
            ['session' => $session['id'] ?? null],
        );
    }
}
