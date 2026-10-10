<?php

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Contracts\RepliesToWebhook;
use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Payments\CheckoutResult;
use App\Modules\Billing\Payments\GatewayConfig;
use App\Modules\Billing\Payments\PaymentNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * PayTR (Turkey) iFrame API. PayTR only accepts alphanumeric order ids, so our reference is sent hex-encoded and
 * decoded again on the callback. The callback ("Bildirim URL") is authenticated with an HMAC and must be answered with
 * the plain text "OK", otherwise PayTR keeps retrying. The browser return carries no data, so only the callback confirms.
 */
class PaytrGateway extends BaseGateway implements RepliesToWebhook
{
    private const API = 'https://www.paytr.com/odeme/api/get-token';

    private const CURRENCIES = ['TRY' => 'TL', 'EUR' => 'EUR', 'USD' => 'USD', 'GBP' => 'GBP', 'RUB' => 'RUB'];

    public function code(): string
    {
        return 'paytr';
    }

    public function name(): string
    {
        return 'PayTR';
    }

    public function fields(): array
    {
        return [
            ['key' => 'merchant_id', 'label' => 'Merchant ID', 'type' => 'text'],
            ['key' => 'merchant_key', 'label' => 'Merchant key', 'type' => 'secret'],
            ['key' => 'merchant_salt', 'label' => 'Merchant salt', 'type' => 'secret'],
            ['key' => 'mode', 'label' => 'Mode', 'type' => 'select', 'options' => ['test' => 'Test', 'live' => 'Live']],
        ];
    }

    public function supportsCurrency(string $currency): bool
    {
        return isset(self::CURRENCIES[strtoupper($currency)]);
    }

    public function checkout(Invoice $invoice, GatewayConfig $config, string $returnUrl, string $cancelUrl, string $webhookUrl): CheckoutResult
    {
        $oid = bin2hex($invoice->number);
        $amount = $this->minor($invoice);
        $ip = (string) (request()->ip() ?: '127.0.0.1');
        $email = $this->buyerEmail($invoice) ?? 'guest@example.com';
        $basket = base64_encode(json_encode([[mb_substr($this->description($invoice), 0, 60), number_format($amount / 100, 2, '.', ''), 1]]));
        $currency = self::CURRENCIES[strtoupper($invoice->currency_code)];
        $test = $config->get('mode') === 'live' ? '0' : '1';

        $token = base64_encode(hash_hmac('sha256', $config->get('merchant_id').$ip.$oid.$email.$amount.$basket.'0'.'0'.$currency.$test.$config->get('merchant_salt'), (string) $config->get('merchant_key'), true));

        $data = $this->json($this->http()->asForm()->post(self::API, [
            'merchant_id' => $config->get('merchant_id'),
            'user_ip' => $ip,
            'merchant_oid' => $oid,
            'email' => $email,
            'payment_amount' => $amount,
            'paytr_token' => $token,
            'user_basket' => $basket,
            'debug_on' => $test,
            'no_installment' => 0,
            'max_installment' => 0,
            'user_name' => $invoice->billing['buyer']['name'] ?? 'Guest',
            'user_address' => $invoice->billing['buyer']['address'] ?? 'N/A',
            'user_phone' => $invoice->billing['buyer']['phone'] ?? '0000000000',
            'merchant_ok_url' => $returnUrl,
            'merchant_fail_url' => $cancelUrl,
            'timeout_limit' => 30,
            'currency' => $currency,
            'test_mode' => $test,
            'lang' => app()->getLocale() === 'tr' ? 'tr' : 'en',
        ]), 'checkout');

        if (($data['status'] ?? null) !== 'success' || empty($data['token'])) {
            throw new GatewayException('PayTR: '.($data['reason'] ?? 'no payment token returned.'));
        }

        return new CheckoutResult('https://www.paytr.com/odeme/guvenli/'.rawurlencode((string) $data['token']), $oid);
    }

    public function webhook(Request $request, GatewayConfig $config): ?PaymentNotification
    {
        $oid = (string) $request->input('merchant_oid');
        $status = (string) $request->input('status');
        $total = (string) $request->input('total_amount');

        $expected = base64_encode(hash_hmac('sha256', $oid.$config->get('merchant_salt').$status.$total, (string) $config->get('merchant_key'), true));

        if (! $this->signatureMatches($expected, (string) $request->input('hash'))) {
            throw new GatewayException('PayTR: invalid callback hash.');
        }

        $reference = ctype_xdigit($oid) && strlen($oid) % 2 === 0 ? hex2bin($oid) : false;

        if ($reference === false) {
            throw new GatewayException('PayTR: unreadable order id.');
        }

        $currency = array_search((string) $request->input('payment_currency', 'TL'), self::CURRENCIES, true) ?: 'TRY';

        return new PaymentNotification('paytr', $reference, $oid, $status === 'success' ? PaymentNotification::SUCCEEDED : PaymentNotification::FAILED,
            (int) $request->input('payment_amount', $total), $currency, ['status' => $status]);
    }

    public function confirmReturn(Request $request, Invoice $invoice, GatewayConfig $config): ?PaymentNotification
    {
        return null;
    }

    public function webhookReply(): Response
    {
        return response('OK', 200)->header('Content-Type', 'text/plain');
    }
}
