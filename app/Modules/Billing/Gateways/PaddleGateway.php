<?php

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Payments\CheckoutResult;
use App\Modules\Billing\Payments\GatewayConfig;
use App\Modules\Billing\Payments\PaymentNotification;
use Illuminate\Http\Request;

/**
 * Paddle Billing (merchant of record). A transaction is created for the invoice amount; its webhook is signed
 * (Paddle-Signature: ts=...;h1=hmac) and replay-limited to five minutes.
 */
class PaddleGateway extends BaseGateway
{
    private const TOLERANCE = 300;

    public function code(): string
    {
        return 'paddle';
    }

    public function name(): string
    {
        return 'Paddle';
    }

    public function fields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API key', 'type' => 'secret'],
            ['key' => 'webhook_secret', 'label' => 'Notification secret key', 'type' => 'secret'],
            ['key' => 'environment', 'label' => 'Environment', 'type' => 'select', 'options' => ['production' => 'Production', 'sandbox' => 'Sandbox'], 'optional' => true],
        ];
    }

    public function checkout(Invoice $invoice, GatewayConfig $config, string $returnUrl, string $cancelUrl, string $webhookUrl): CheckoutResult
    {
        $data = $this->json($this->http()->withToken((string) $config->get('api_key'))->post($this->api($config).'/transactions', [
            'items' => [['quantity' => 1, 'price' => [
                'description' => mb_substr($this->description($invoice), 0, 200),
                'unit_price' => ['amount' => (string) $this->minor($invoice), 'currency_code' => strtoupper($invoice->currency_code)],
                'product' => ['name' => mb_substr($this->description($invoice), 0, 100), 'tax_category' => 'standard'],
            ]]],
            'custom_data' => ['invoice' => $invoice->number],
            'checkout' => ['url' => $returnUrl],
        ]), 'checkout');

        return new CheckoutResult($data['data']['checkout']['url'] ?? throw new GatewayException('Paddle: no checkout URL returned.'), (string) ($data['data']['id'] ?? ''));
    }

    public function webhook(Request $request, GatewayConfig $config): ?PaymentNotification
    {
        $this->verify($request, (string) $config->get('webhook_secret'));
        $event = json_decode($request->getContent(), true);

        return ($event['event_type'] ?? null) === 'transaction.completed' ? $this->notification($event['data'] ?? []) : null;
    }

    public function confirmReturn(Request $request, Invoice $invoice, GatewayConfig $config): ?PaymentNotification
    {
        $id = Payment::allTenants()->where('invoice_id', $invoice->id)->where('gateway', 'paddle')->latest('id')->value('transaction_id');

        if (! $id || ! preg_match('/^txn_[a-z0-9]+$/', $id)) {
            return null;
        }

        $data = $this->json($this->http()->withToken((string) $config->get('api_key'))->get($this->api($config).'/transactions/'.$id), 'lookup')['data'] ?? [];
        $notification = in_array($data['status'] ?? null, ['completed', 'paid'], true) ? $this->notification($data) : null;

        return $notification && $notification->invoiceNumber === $invoice->number ? $notification : null;
    }

    /** @param array<string, mixed> $tx */
    private function notification(array $tx): ?PaymentNotification
    {
        if (empty($tx['id']) || empty($tx['custom_data']['invoice'])) {
            return null;
        }

        return new PaymentNotification('paddle', (string) $tx['custom_data']['invoice'], (string) $tx['id'], PaymentNotification::SUCCEEDED,
            (int) ($tx['details']['totals']['grand_total'] ?? $tx['details']['totals']['total'] ?? 0), strtoupper((string) ($tx['currency_code'] ?? '')), ['status' => $tx['status'] ?? null]);
    }

    private function verify(Request $request, string $secret): void
    {
        $header = (string) $request->header('Paddle-Signature');
        $parts = [];

        foreach (explode(';', $header) as $part) {
            [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$k] = $v;
        }

        $ts = (int) ($parts['ts'] ?? 0);

        if ($ts === 0 || abs(time() - $ts) > self::TOLERANCE || ! $this->signatureMatches(hash_hmac('sha256', $ts.':'.$request->getContent(), $secret), (string) ($parts['h1'] ?? ''))) {
            throw new GatewayException('Paddle: invalid webhook signature.');
        }
    }

    private function api(GatewayConfig $c): string
    {
        return $c->get('environment') === 'sandbox' ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';
    }
}
