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
 * Mollie. Its webhook only carries a payment id and is unsigned by design, so the payment is always
 * fetched from the API and only that response is trusted.
 */
class MollieGateway extends BaseGateway
{
    private const API = 'https://api.mollie.com/v2';

    public function code(): string
    {
        return 'mollie';
    }

    public function name(): string
    {
        return 'Mollie';
    }

    public function fields(): array
    {
        return [['key' => 'api_key', 'label' => 'API key (live_... / test_...)', 'type' => 'secret']];
    }

    public function checkout(Invoice $invoice, GatewayConfig $config, string $returnUrl, string $cancelUrl, string $webhookUrl): CheckoutResult
    {
        $data = $this->json($this->http()->withToken($config->get('api_key'))->post(self::API.'/payments', [
            'amount' => ['currency' => strtoupper($invoice->currency_code), 'value' => Money::decimalString($invoice->total, $invoice->currency_code)],
            'description' => $invoice->number.' - '.$this->description($invoice),
            'redirectUrl' => $returnUrl,
            'cancelUrl' => $cancelUrl,
            'webhookUrl' => $webhookUrl,
            'metadata' => ['invoice' => $invoice->number],
        ]), 'checkout');

        return new CheckoutResult($data['_links']['checkout']['href'] ?? throw new GatewayException('Mollie: no checkout URL returned.'), (string) $data['id']);
    }

    public function webhook(Request $request, GatewayConfig $config): ?PaymentNotification
    {
        $id = (string) $request->input('id');

        if (! preg_match('/^tr_[A-Za-z0-9]+$/', $id)) {
            throw new GatewayException('Mollie: malformed payment id.');
        }

        return $this->fetch($id, $config);
    }

    public function confirmReturn(Request $request, Invoice $invoice, GatewayConfig $config): ?PaymentNotification
    {
        $id = Payment::allTenants()->where('invoice_id', $invoice->id)->where('gateway', 'mollie')->latest('id')->value('transaction_id');

        $notification = $id ? $this->fetch($id, $config) : null;

        return $notification && $notification->invoiceNumber === $invoice->number ? $notification : null;
    }

    private function fetch(string $id, GatewayConfig $config): ?PaymentNotification
    {
        $payment = $this->json($this->http()->withToken($config->get('api_key'))->get(self::API.'/payments/'.$id), 'payment lookup');

        $status = match ($payment['status'] ?? null) {
            'paid' => PaymentNotification::SUCCEEDED,
            'failed', 'canceled', 'expired' => PaymentNotification::FAILED,
            default => null, // open / pending: wait for the next callback
        };

        if ($status === null) {
            return null;
        }

        $currency = strtoupper((string) ($payment['amount']['currency'] ?? ''));

        return new PaymentNotification('mollie', (string) ($payment['metadata']['invoice'] ?? ''), $id, $status,
            Money::toMinor($payment['amount']['value'] ?? 0, $currency), $currency, ['status' => $payment['status']]);
    }
}
