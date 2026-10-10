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
 * iyzico Checkout Form. The customer is sent back with a POSTed token which is exchanged for the
 * payment result server-side; nothing in the browser request is trusted. Requests are signed with
 * the IYZWSv2 scheme (HMAC-SHA256 over randomKey + uri + body).
 */
class IyzicoGateway extends BaseGateway
{
    private const INIT = '/payment/iyzipos/checkoutform/initialize/auth/ecom';

    private const DETAIL = '/payment/iyzipos/checkoutform/auth/ecom/detail';

    public function code(): string
    {
        return 'iyzico';
    }

    public function name(): string
    {
        return 'iyzico';
    }

    public function fields(): array
    {
        return [
            ['key' => 'mode', 'label' => 'Mode', 'type' => 'select', 'options' => ['test' => 'Sandbox', 'live' => 'Live']],
            ['key' => 'api_key', 'label' => 'API key', 'type' => 'secret'],
            ['key' => 'secret_key', 'label' => 'Secret key', 'type' => 'secret'],
        ];
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), ['TRY', 'USD', 'EUR', 'GBP', 'IRR', 'NOK', 'RUB', 'CHF'], true);
    }

    public function checkout(Invoice $invoice, GatewayConfig $config, string $returnUrl, string $cancelUrl, string $webhookUrl): CheckoutResult
    {
        $price = Money::decimalString($invoice->total, $invoice->currency_code);
        $email = $this->buyerEmail($invoice) ?? throw new GatewayException('iyzico: the restaurant has no owner e-mail address.');
        $name = (string) ($invoice->billing['buyer']['name'] ?? 'Customer');
        $address = (string) ($invoice->billing['buyer']['address'] ?: 'N/A');

        $party = ['city' => 'N/A', 'country' => 'N/A', 'address' => $address];

        $result = $this->call(self::INIT, [
            'locale' => 'en',
            'conversationId' => $invoice->number,
            'price' => $price,
            'paidPrice' => $price,
            'currency' => strtoupper($invoice->currency_code),
            'basketId' => $invoice->number,
            'paymentGroup' => 'PRODUCT',
            'callbackUrl' => $returnUrl,
            'enabledInstallments' => [1],
            'buyer' => [
                'id' => (string) $invoice->restaurant_id, 'name' => $name, 'surname' => '-', 'email' => $email,
                'identityNumber' => '11111111111', 'registrationAddress' => $address, 'ip' => request()->ip() ?? '127.0.0.1',
                'city' => 'N/A', 'country' => 'N/A',
            ],
            'shippingAddress' => $party + ['contactName' => $name],
            'billingAddress' => $party + ['contactName' => $name],
            'basketItems' => [['id' => (string) $invoice->plan_id, 'name' => $this->description($invoice), 'category1' => 'Subscription', 'itemType' => 'VIRTUAL', 'price' => $price]],
        ], $config);

        $url = $result['paymentPageUrl'] ?? throw new GatewayException('iyzico: no payment page returned.');

        return new CheckoutResult($url, (string) ($result['token'] ?? $invoice->number));
    }

    /** iyzico has no signed webhook in this integration: the return callback is the confirmation. */
    public function webhook(Request $request, GatewayConfig $config): ?PaymentNotification
    {
        return null;
    }

    public function confirmReturn(Request $request, Invoice $invoice, GatewayConfig $config): ?PaymentNotification
    {
        $token = (string) $request->input('token');

        if (! preg_match('/^[A-Za-z0-9-]{10,80}$/', $token)) {
            return null;
        }

        $detail = $this->call(self::DETAIL, ['locale' => 'en', 'conversationId' => $invoice->number, 'token' => $token], $config);

        if (($detail['paymentStatus'] ?? null) !== 'SUCCESS' || ($detail['basketId'] ?? null) !== $invoice->number) {
            return null;
        }

        $currency = strtoupper((string) ($detail['currency'] ?? ''));

        return new PaymentNotification('iyzico', $invoice->number, (string) ($detail['paymentId'] ?? $token), PaymentNotification::SUCCEEDED,
            Money::toMinor($detail['paidPrice'] ?? 0, $currency), $currency, ['payment' => $detail['paymentId'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(string $path, array $payload, GatewayConfig $config): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $random = (string) (int) (microtime(true) * 1000).random_int(1000, 9999);
        $signature = hash_hmac('sha256', $random.$path.$body, (string) $config->get('secret_key'));
        $authorization = base64_encode('apiKey:'.$config->get('api_key').'&randomKey:'.$random.'&signature:'.$signature);
        $base = $config->isLive() ? 'https://api.iyzipay.com' : 'https://sandbox-api.iyzipay.com';

        $response = $this->http()->withHeaders(['Authorization' => 'IYZWSv2 '.$authorization, 'x-iyzi-rnd' => $random])
            ->withBody($body, 'application/json')->post($base.$path);

        $data = $this->json($response, 'request');

        // iyzico reports business failures with HTTP 200 and status "failure".
        if (($data['status'] ?? null) !== 'success') {
            throw new GatewayException('iyzico: '.($data['errorMessage'] ?? 'request rejected'));
        }

        return $data;
    }
}
