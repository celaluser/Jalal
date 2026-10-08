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
 * Mercado Pago Checkout Pro (Latin America). Notifications only carry a payment id, so the payment is always fetched
 * with our access token and only that response is trusted.
 */
class MercadoPagoGateway extends BaseGateway
{
    private const API = 'https://api.mercadopago.com';

    public function code(): string
    {
        return 'mercadopago';
    }

    public function name(): string
    {
        return 'Mercado Pago';
    }

    public function fields(): array
    {
        return [['key' => 'access_token', 'label' => 'Access token (APP_USR-...)', 'type' => 'secret']];
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), ['ARS', 'BRL', 'CLP', 'COP', 'MXN', 'PEN', 'UYU'], true);
    }

    public function checkout(Invoice $invoice, GatewayConfig $config, string $returnUrl, string $cancelUrl, string $webhookUrl): CheckoutResult
    {
        $data = $this->json($this->http()->withToken((string) $config->get('access_token'))->post(self::API.'/checkout/preferences', [
            'items' => [['title' => mb_substr($this->description($invoice), 0, 120), 'quantity' => 1, 'currency_id' => strtoupper($invoice->currency_code), 'unit_price' => (float) Money::decimalString($invoice->total, $invoice->currency_code)]],
            'external_reference' => $invoice->number,
            'back_urls' => ['success' => $returnUrl, 'pending' => $returnUrl, 'failure' => $cancelUrl],
            'auto_return' => 'approved',
            'notification_url' => $webhookUrl,
        ]), 'checkout');

        return new CheckoutResult($data['init_point'] ?? throw new GatewayException('Mercado Pago: no payment URL returned.'), (string) $data['id']);
    }

    public function webhook(Request $request, GatewayConfig $config): ?PaymentNotification
    {
        $type = (string) ($request->input('type') ?? $request->input('topic'));
        $id = (string) ($request->input('data.id') ?? $request->input('id'));

        if ($type !== 'payment') {
            return null;
        }

        if (! preg_match('/^\d{3,20}$/', $id)) {
            throw new GatewayException('Mercado Pago: malformed payment id.');
        }

        return $this->fetch($id, $config);
    }

    public function confirmReturn(Request $request, Invoice $invoice, GatewayConfig $config): ?PaymentNotification
    {
        $id = (string) ($request->query('payment_id') ?? $request->query('collection_id'));

        if (! preg_match('/^\d{3,20}$/', $id)) {
            return null;
        }

        $notification = $this->fetch($id, $config);

        return $notification && $notification->invoiceNumber === $invoice->number ? $notification : null;
    }

    private function fetch(string $id, GatewayConfig $config): ?PaymentNotification
    {
        $p = $this->json($this->http()->withToken((string) $config->get('access_token'))->get(self::API.'/v1/payments/'.$id), 'payment lookup');

        $status = match ($p['status'] ?? null) {
            'approved' => PaymentNotification::SUCCEEDED,
            'rejected', 'cancelled' => PaymentNotification::FAILED,
            default => null,
        };

        if ($status === null) {
            return null;
        }

        $currency = strtoupper((string) ($p['currency_id'] ?? ''));

        return new PaymentNotification('mercadopago', (string) ($p['external_reference'] ?? ''), $id, $status, Money::toMinor($p['transaction_amount'] ?? 0, $currency), $currency, ['status' => $p['status']]);
    }
}
