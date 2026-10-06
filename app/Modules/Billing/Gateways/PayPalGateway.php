<?php

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Payments\CheckoutResult;
use App\Modules\Billing\Payments\GatewayConfig;
use App\Modules\Billing\Payments\PaymentNotification;
use App\Modules\Billing\Support\Money;
use Illuminate\Http\Request;

/**
 * PayPal Orders v2. The customer approves on PayPal, then our server captures the order on return,
 * which is the authoritative confirmation. The optional webhook is verified through PayPal's
 * own verify-webhook-signature call.
 */
class PayPalGateway extends BaseGateway
{
    public function code(): string
    {
        return 'paypal';
    }

    public function name(): string
    {
        return 'PayPal';
    }

    public function fields(): array
    {
        return [
            ['key' => 'mode', 'label' => 'Mode', 'type' => 'select', 'options' => ['test' => 'Sandbox', 'live' => 'Live']],
            ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text'],
            ['key' => 'client_secret', 'label' => 'Client secret', 'type' => 'secret'],
            ['key' => 'webhook_id', 'label' => 'Webhook ID (optional)', 'type' => 'text', 'optional' => true],
        ];
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), ['AUD', 'BRL', 'CAD', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY', 'MYR', 'MXN', 'TWD', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'SGD', 'SEK', 'CHF', 'THB', 'USD'], true);
    }

    public function checkout(Invoice $invoice, GatewayConfig $config, string $returnUrl, string $cancelUrl, string $webhookUrl): CheckoutResult
    {
        $order = $this->json($this->authed($config)->withHeaders(['PayPal-Request-Id' => $invoice->number])->post($this->base($config).'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $invoice->number,
                'invoice_id' => $invoice->number,
                'description' => $this->description($invoice),
                'amount' => ['currency_code' => strtoupper($invoice->currency_code), 'value' => Money::decimalString($invoice->total, $invoice->currency_code)],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'return_url' => $returnUrl, 'cancel_url' => $cancelUrl, 'user_action' => 'PAY_NOW', 'shipping_preference' => 'NO_SHIPPING',
            ]]],
        ]), 'checkout');

        $link = collect($order['links'] ?? [])->first(fn ($l) => in_array($l['rel'] ?? '', ['payer-action', 'approve'], true));

        return new CheckoutResult($link['href'] ?? throw new GatewayException('PayPal: no approval URL returned.'), (string) $order['id']);
    }

    public function webhook(Request $request, GatewayConfig $config): ?PaymentNotification
    {
        $webhookId = $config->get('webhook_id');

        if (! $webhookId) {
            return null; // confirmation then happens when the customer returns
        }

        $event = $request->json()->all();

        $verification = $this->json($this->authed($config)->post($this->base($config).'/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url' => $request->header('PAYPAL-CERT-URL'),
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            'webhook_id' => $webhookId,
            'webhook_event' => $event,
        ]), 'webhook verification');

        if (($verification['verification_status'] ?? null) !== 'SUCCESS') {
            throw new GatewayException('PayPal: invalid webhook signature.');
        }

        return ($event['event_type'] ?? null) === 'PAYMENT.CAPTURE.COMPLETED' ? $this->fromCapture($event['resource'] ?? []) : null;
    }

    public function confirmReturn(Request $request, Invoice $invoice, GatewayConfig $config): ?PaymentNotification
    {
        $orderId = (string) $request->query('token');
        $expected = Payment::allTenants()->where('invoice_id', $invoice->id)->where('gateway', 'paypal')->latest('id')->value('transaction_id');

        // Only the order we created for this invoice may be captured for it.
        if (! preg_match('/^[A-Z0-9]{8,30}$/', $orderId) || $orderId !== $expected) {
            return null;
        }

        $response = $this->authed($config)->withBody('{}', 'application/json')->post($this->base($config)."/v2/checkout/orders/{$orderId}/capture");

        // Reloading the return page must not fail: an already captured order is simply read back.
        if ($response->status() === 422 && str_contains((string) $response->body(), 'ORDER_ALREADY_CAPTURED')) {
            $response = $this->authed($config)->get($this->base($config)."/v2/checkout/orders/{$orderId}");
        }

        $order = $this->json($response, 'capture');

        if (($order['status'] ?? null) !== 'COMPLETED') {
            return null;
        }

        return $this->fromCapture($order['purchase_units'][0]['payments']['captures'][0] ?? []);
    }

    /** @param array<string, mixed> $capture */
    private function fromCapture(array $capture): ?PaymentNotification
    {
        if (($capture['status'] ?? null) !== 'COMPLETED') {
            return null;
        }

        $currency = strtoupper((string) ($capture['amount']['currency_code'] ?? ''));

        return new PaymentNotification('paypal', (string) ($capture['invoice_id'] ?? ''), (string) ($capture['id'] ?? ''), PaymentNotification::SUCCEEDED,
            Money::toMinor($capture['amount']['value'] ?? 0, $currency), $currency, ['capture' => $capture['id'] ?? null]);
    }

    private function base(GatewayConfig $config): string
    {
        return $config->isLive() ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    private function authed(GatewayConfig $config)
    {
        $token = $this->json(
            $this->http()->withBasicAuth((string) $config->get('client_id'), (string) $config->get('client_secret'))->asForm()
                ->post($this->base($config).'/v1/oauth2/token', ['grant_type' => 'client_credentials']),
            'authentication'
        )['access_token'] ?? throw new GatewayException('PayPal: authentication failed.');

        return $this->http()->withToken($token);
    }
}
