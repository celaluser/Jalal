<?php

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Payments\CheckoutResult;
use App\Modules\Billing\Payments\GatewayConfig;
use App\Modules\Billing\Payments\PaymentNotification;
use Illuminate\Http\Request;

/** Razorpay Payment Links. Webhook: HMAC-SHA256 of the body (X-Razorpay-Signature). */
class RazorpayGateway extends BaseGateway
{
    private const API = 'https://api.razorpay.com/v1';

    public function code(): string
    {
        return 'razorpay';
    }

    public function name(): string
    {
        return 'Razorpay';
    }

    public function fields(): array
    {
        return [
            ['key' => 'key_id', 'label' => 'Key ID (rzp_...)', 'type' => 'text'],
            ['key' => 'key_secret', 'label' => 'Key secret', 'type' => 'secret'],
            ['key' => 'webhook_secret', 'label' => 'Webhook secret', 'type' => 'secret'],
        ];
    }

    public function checkout(Invoice $invoice, GatewayConfig $config, string $returnUrl, string $cancelUrl, string $webhookUrl): CheckoutResult
    {
        $link = $this->json($this->auth($config)->post(self::API.'/payment_links', [
            'amount' => $this->minor($invoice),
            'currency' => strtoupper($invoice->currency_code),
            'reference_id' => $invoice->number,
            'description' => $this->description($invoice),
            'customer' => array_filter(['name' => $invoice->billing['buyer']['name'] ?? null, 'email' => $this->buyerEmail($invoice)]),
            'callback_url' => $returnUrl,
            'callback_method' => 'get',
        ]), 'checkout');

        return new CheckoutResult($link['short_url'] ?? throw new GatewayException('Razorpay: no payment link returned.'), (string) $link['id']);
    }

    public function webhook(Request $request, GatewayConfig $config): ?PaymentNotification
    {
        $expected = hash_hmac('sha256', $request->getContent(), (string) $config->get('webhook_secret'));

        if (! $this->signatureMatches($expected, (string) $request->header('X-Razorpay-Signature'))) {
            throw new GatewayException('Razorpay: invalid webhook signature.');
        }

        $event = json_decode($request->getContent(), true);

        if (($event['event'] ?? null) !== 'payment_link.paid') {
            return null;
        }

        $link = $event['payload']['payment_link']['entity'] ?? [];

        return $this->notification($link, (string) ($event['payload']['payment']['entity']['id'] ?? $link['id'] ?? ''));
    }

    public function confirmReturn(Request $request, Invoice $invoice, GatewayConfig $config): ?PaymentNotification
    {
        $linkId = (string) $request->query('razorpay_payment_link_id');
        $paymentId = (string) $request->query('razorpay_payment_id');

        // The callback query is signed with the key secret: reference and status cannot be forged.
        $signed = implode('|', [$linkId, $request->query('razorpay_payment_link_reference_id'), $request->query('razorpay_payment_link_status'), $paymentId]);

        if (! $this->signatureMatches(hash_hmac('sha256', $signed, (string) $config->get('key_secret')), (string) $request->query('razorpay_signature'))) {
            return null;
        }

        // Amounts are read from Razorpay, not from the (signed but amount-less) callback.
        $link = $this->json($this->auth($config)->get(self::API.'/payment_links/'.rawurlencode($linkId)), 'payment link lookup');

        return $this->notification($link, $paymentId);
    }

    /** @param array<string, mixed> $link */
    private function notification(array $link, string $paymentId): ?PaymentNotification
    {
        if (($link['status'] ?? null) !== 'paid') {
            return null;
        }

        return new PaymentNotification('razorpay', (string) ($link['reference_id'] ?? ''), $paymentId, PaymentNotification::SUCCEEDED,
            (int) ($link['amount_paid'] ?? $link['amount'] ?? 0), strtoupper((string) ($link['currency'] ?? '')), ['link' => $link['id'] ?? null]);
    }

    private function auth(GatewayConfig $config)
    {
        return $this->http()->withBasicAuth((string) $config->get('key_id'), (string) $config->get('key_secret'));
    }
}
