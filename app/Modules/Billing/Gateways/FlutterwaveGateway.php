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
 * Flutterwave Standard. The webhook is authenticated by the shared "verif-hash" header and its
 * content is never trusted: the transaction is re-fetched from the API before it counts.
 */
class FlutterwaveGateway extends BaseGateway
{
    private const API = 'https://api.flutterwave.com/v3';

    public function code(): string
    {
        return 'flutterwave';
    }

    public function name(): string
    {
        return 'Flutterwave';
    }

    public function fields(): array
    {
        return [
            ['key' => 'secret_key', 'label' => 'Secret key (FLWSECK-...)', 'type' => 'secret'],
            ['key' => 'secret_hash', 'label' => 'Webhook secret hash', 'type' => 'secret'],
        ];
    }

    public function checkout(Invoice $invoice, GatewayConfig $config, string $returnUrl, string $cancelUrl, string $webhookUrl): CheckoutResult
    {
        $email = $this->buyerEmail($invoice) ?? throw new GatewayException('Flutterwave: the restaurant has no owner e-mail address.');

        $data = $this->json($this->http()->withToken($config->get('secret_key'))->post(self::API.'/payments', [
            'tx_ref' => $invoice->number,
            'amount' => Money::decimalString($invoice->total, $invoice->currency_code),
            'currency' => strtoupper($invoice->currency_code),
            'redirect_url' => $returnUrl,
            'customer' => ['email' => $email, 'name' => $invoice->billing['buyer']['name'] ?? $email],
            'customizations' => ['title' => $this->description($invoice)],
        ]), 'checkout');

        return new CheckoutResult($data['data']['link'] ?? throw new GatewayException('Flutterwave: no payment link returned.'), $invoice->number);
    }

    public function webhook(Request $request, GatewayConfig $config): ?PaymentNotification
    {
        if (! $this->signatureMatches((string) $config->get('secret_hash'), (string) $request->header('verif-hash'))) {
            throw new GatewayException('Flutterwave: invalid webhook signature.');
        }

        $event = $request->json()->all();
        $id = $event['data']['id'] ?? null;

        if (($event['event'] ?? null) !== 'charge.completed' || ! is_numeric($id)) {
            return null;
        }

        return $this->verified((int) $id, $config);
    }

    public function confirmReturn(Request $request, Invoice $invoice, GatewayConfig $config): ?PaymentNotification
    {
        $id = $request->query('transaction_id');

        if (! is_numeric($id) || $request->query('status') === 'cancelled') {
            return null;
        }

        $notification = $this->verified((int) $id, $config);

        return $notification && $notification->invoiceNumber === $invoice->number ? $notification : null;
    }

    private function verified(int $transactionId, GatewayConfig $config): ?PaymentNotification
    {
        $tx = $this->json($this->http()->withToken($config->get('secret_key'))->get(self::API."/transactions/{$transactionId}/verify"), 'verification')['data'] ?? [];

        if (($tx['status'] ?? null) !== 'successful') {
            return null;
        }

        $currency = strtoupper((string) ($tx['currency'] ?? ''));

        return new PaymentNotification('flutterwave', (string) ($tx['tx_ref'] ?? ''), (string) $transactionId, PaymentNotification::SUCCEEDED,
            Money::toMinor($tx['amount'] ?? 0, $currency), $currency, ['flw_ref' => $tx['flw_ref'] ?? null]);
    }
}
