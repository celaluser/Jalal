<?php

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Payments\CheckoutResult;
use App\Modules\Billing\Payments\GatewayConfig;
use App\Modules\Billing\Payments\PaymentNotification;
use Illuminate\Http\Request;

/**
 * Epoint (Azerbaijan). Every request and callback carries `data` (base64 JSON) and `signature`
 * (base64 of SHA-1 over private_key + data + private_key). The return is confirmed by asking Epoint for the status.
 */
class EpointGateway extends BaseGateway
{
    private const API = 'https://epoint.az/api/1';

    public function code(): string
    {
        return 'epoint';
    }

    public function name(): string
    {
        return 'Epoint (Azerbaijan)';
    }

    public function fields(): array
    {
        return [
            ['key' => 'public_key', 'label' => 'Public key (i0000...)', 'type' => 'text'],
            ['key' => 'private_key', 'label' => 'Private key', 'type' => 'secret'],
        ];
    }

    public function supportsCurrency(string $currency): bool
    {
        return strtoupper($currency) === 'AZN';
    }

    public function checkout(Invoice $invoice, GatewayConfig $config, string $returnUrl, string $cancelUrl, string $webhookUrl): CheckoutResult
    {
        $payload = [
            'public_key' => $config->get('public_key'),
            'amount' => round($this->minor($invoice) / 100, 2),
            'currency' => 'AZN',
            'language' => in_array(app()->getLocale(), ['az', 'ru', 'en'], true) ? app()->getLocale() : 'en',
            'order_id' => $invoice->number,
            'description' => mb_substr($this->description($invoice), 0, 200),
            'success_redirect_url' => $returnUrl,
            'error_redirect_url' => $cancelUrl,
            'result_url' => $webhookUrl,
        ];

        $data = $this->json($this->http()->asForm()->post(self::API.'/request', $this->sign($payload, $config)), 'checkout');

        if (($data['status'] ?? null) !== 'success' || empty($data['redirect_url'])) {
            throw new GatewayException('Epoint: '.(is_string($data['message'] ?? null) ? $data['message'] : 'no payment URL returned.'));
        }

        return new CheckoutResult((string) $data['redirect_url'], (string) ($data['transaction'] ?? $invoice->number));
    }

    public function webhook(Request $request, GatewayConfig $config): ?PaymentNotification
    {
        $data = (string) $request->input('data');
        $expected = $this->signature($data, $config);

        if (! $this->signatureMatches($expected, (string) $request->input('signature'))) {
            throw new GatewayException('Epoint: invalid callback signature.');
        }

        $result = json_decode((string) base64_decode($data, true), true);

        return is_array($result) ? $this->notification($result) : throw new GatewayException('Epoint: unreadable callback.');
    }

    public function confirmReturn(Request $request, Invoice $invoice, GatewayConfig $config): ?PaymentNotification
    {
        $answer = $this->json($this->http()->asForm()->post(self::API.'/get-status', $this->sign(['public_key' => $config->get('public_key'), 'order_id' => $invoice->number], $config)), 'verification');

        return ($answer['status'] ?? null) === 'success' ? $this->notification($answer + ['order_id' => $invoice->number, 'amount' => round($this->minor($invoice) / 100, 2)]) : null;
    }

    /** @param array<string, mixed> $result */
    private function notification(array $result): ?PaymentNotification
    {
        $status = (string) ($result['status'] ?? '');

        if ($status === 'new' || $status === '') {
            return null;
        }

        return new PaymentNotification('epoint', (string) ($result['order_id'] ?? ''), (string) ($result['transaction'] ?? $result['order_id'] ?? ''),
            $status === 'success' ? PaymentNotification::SUCCEEDED : PaymentNotification::FAILED, (int) round(((float) ($result['amount'] ?? 0)) * 100), 'AZN', ['status' => $status]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{data: string, signature: string}
     */
    private function sign(array $payload, GatewayConfig $config): array
    {
        $data = base64_encode(json_encode($payload));

        return ['data' => $data, 'signature' => $this->signature($data, $config)];
    }

    private function signature(string $data, GatewayConfig $config): string
    {
        $key = (string) $config->get('private_key');

        return base64_encode(sha1($key.$data.$key, true));
    }
}
