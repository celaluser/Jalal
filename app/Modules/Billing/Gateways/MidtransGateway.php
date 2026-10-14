<?php

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Payments\CheckoutResult;
use App\Modules\Billing\Payments\GatewayConfig;
use App\Modules\Billing\Payments\PaymentNotification;
use App\Modules\Billing\Support\Money;
use Illuminate\Http\Request;

/**
 * Midtrans Snap (Indonesia). The HTTP notification is signed with SHA-512 over order id, status code, amount and the
 * server key; the return page is verified by asking Midtrans for the transaction status.
 */
class MidtransGateway extends BaseGateway
{
    public function code(): string
    {
        return 'midtrans';
    }

    public function name(): string
    {
        return 'Midtrans';
    }

    public function fields(): array
    {
        return [
            ['key' => 'server_key', 'label' => 'Server key', 'type' => 'secret'],
            ['key' => 'environment', 'label' => 'Environment', 'type' => 'select', 'options' => ['production' => 'Production', 'sandbox' => 'Sandbox'], 'optional' => true],
        ];
    }

    public function supportsCurrency(string $currency): bool
    {
        return strtoupper($currency) === 'IDR';
    }

    public function checkout(Invoice $invoice, GatewayConfig $config, string $returnUrl, string $cancelUrl, string $webhookUrl): CheckoutResult
    {
        $data = $this->json($this->http()->withBasicAuth((string) $config->get('server_key'), '')->post($this->snap($config).'/snap/v1/transactions', [
            'transaction_details' => ['order_id' => $invoice->number, 'gross_amount' => (int) round($invoice->total)],
            'customer_details' => array_filter(['email' => $this->buyerEmail($invoice)]),
            'item_details' => [['id' => $invoice->number, 'price' => (int) round($invoice->total), 'quantity' => 1, 'name' => mb_substr($this->description($invoice), 0, 50)]],
            'callbacks' => ['finish' => $returnUrl],
        ]), 'checkout');

        return new CheckoutResult($data['redirect_url'] ?? throw new GatewayException('Midtrans: no payment URL returned.'), (string) ($data['token'] ?? $invoice->number));
    }

    public function webhook(Request $request, GatewayConfig $config): ?PaymentNotification
    {
        $e = json_decode($request->getContent(), true);

        if (! is_array($e)) {
            throw new GatewayException('Midtrans: unreadable notification.');
        }

        $expected = hash('sha512', ($e['order_id'] ?? '').($e['status_code'] ?? '').($e['gross_amount'] ?? '').$config->get('server_key'));

        if (! $this->signatureMatches($expected, (string) ($e['signature_key'] ?? ''))) {
            throw new GatewayException('Midtrans: invalid notification signature.');
        }

        return $this->notification($e);
    }

    public function confirmReturn(Request $request, Invoice $invoice, GatewayConfig $config): ?PaymentNotification
    {
        $status = $this->json($this->http()->withBasicAuth((string) $config->get('server_key'), '')->get($this->api($config).'/v2/'.rawurlencode($invoice->number).'/status'), 'status lookup');

        return ($status['order_id'] ?? null) === $invoice->number ? $this->notification($status) : null;
    }

    /** @param array<string, mixed> $e */
    private function notification(array $e): ?PaymentNotification
    {
        $state = $e['transaction_status'] ?? '';
        $status = match (true) {
            $state === 'settlement', $state === 'capture' && ($e['fraud_status'] ?? 'accept') === 'accept' => PaymentNotification::SUCCEEDED,
            in_array($state, ['deny', 'cancel', 'expire', 'failure'], true) => PaymentNotification::FAILED,
            default => null, // pending: wait for the next notification
        };

        if ($status === null) {
            return null;
        }

        // Midtrans reports whole rupiah ("10000.00"); the converter turns it into the same minor units the invoice uses.
        return new PaymentNotification('midtrans', (string) $e['order_id'], (string) ($e['transaction_id'] ?? $e['order_id']), $status,
            Money::toMinor($e['gross_amount'] ?? 0, 'IDR'), 'IDR', ['transaction_status' => $state]);
    }

    private function snap(GatewayConfig $c): string
    {
        return $c->get('environment') === 'sandbox' ? 'https://app.sandbox.midtrans.com' : 'https://app.midtrans.com';
    }

    private function api(GatewayConfig $c): string
    {
        return $c->get('environment') === 'sandbox' ? 'https://api.sandbox.midtrans.com' : 'https://api.midtrans.com';
    }
}
