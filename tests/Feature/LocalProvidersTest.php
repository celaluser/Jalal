<?php

use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Gateways\EpointGateway;
use App\Modules\Billing\Gateways\PaytrGateway;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Payments\GatewayConfig;
use App\Modules\Billing\Payments\GatewayManager;
use App\Modules\Messaging\Exceptions\MessagingException;
use App\Modules\Messaging\Providers\IletiMerkeziProvider;
use App\Modules\Messaging\Providers\NetgsmProvider;
use App\Modules\Messaging\Services\MessagingManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function lpInvoice(string $currency, float $total = 100): Invoice
{
    return new Invoice(['number' => 'INV-2026-000007', 'currency_code' => $currency, 'total' => $total, 'items' => [['description' => 'Pro']], 'billing' => ['buyer' => ['email' => 'o@example.com']]]);
}

it('registers the local gateways and SMS providers', function () {
    expect(app(GatewayManager::class)->all())->toHaveKeys(['paytr', 'epoint'])
        ->and(app(MessagingManager::class)->all())->toHaveKeys(['netgsm', 'iletimerkezi']);
});

describe('paytr', function () {
    $config = new GatewayConfig(['merchant_id' => '1234', 'merchant_key' => 'key', 'merchant_salt' => 'salt', 'mode' => 'test']);

    it('asks for a token with a hex-encoded alphanumeric order id', function () use ($config) {
        Http::fake(['www.paytr.com/*' => Http::response(['status' => 'success', 'token' => 'tok123'])]);

        $r = (new PaytrGateway)->checkout(lpInvoice('TRY', 150.5), $config, 'https://ok', 'https://fail', 'https://hook');

        expect($r->redirectUrl)->toBe('https://www.paytr.com/odeme/guvenli/tok123')->and($r->reference)->toBe(bin2hex('INV-2026-000007'));
        Http::assertSent(fn ($q) => $q['merchant_oid'] === bin2hex('INV-2026-000007') && $q['payment_amount'] == 15050 && $q['currency'] === 'TL' && ! empty($q['paytr_token']));
    });

    it('surfaces PayTR errors and refuses unsupported currencies', function () use ($config) {
        Http::fake(['www.paytr.com/*' => Http::response(['status' => 'failed', 'reason' => 'bad hash'])]);
        expect(fn () => (new PaytrGateway)->checkout(lpInvoice('TRY'), $config, 'a', 'b', 'c'))->toThrow(GatewayException::class, 'bad hash');
        expect((new PaytrGateway)->supportsCurrency('TRY'))->toBeTrue()->and((new PaytrGateway)->supportsCurrency('AZN'))->toBeFalse();
    });

    it('verifies the callback hash and answers with plain OK', function () use ($config) {
        $gw = new PaytrGateway;
        $oid = bin2hex('INV-2026-000007');
        $hash = base64_encode(hash_hmac('sha256', $oid.'salt'.'success'.'15050', 'key', true));
        $post = fn (array $d) => Request::create('/w', 'POST', $d);

        $n = $gw->webhook($post(['merchant_oid' => $oid, 'status' => 'success', 'total_amount' => '15050', 'payment_amount' => '15050', 'hash' => $hash]), $config);
        expect($n)->toMatchObject(['invoiceNumber' => 'INV-2026-000007', 'status' => 'succeeded', 'amount' => 15050, 'currency' => 'TRY']);
        expect(fn () => $gw->webhook($post(['merchant_oid' => $oid, 'status' => 'success', 'total_amount' => '15050', 'hash' => 'forged']), $config))->toThrow(GatewayException::class);
        expect($gw->webhookReply()->getContent())->toBe('OK');
    });
});

describe('epoint', function () {
    $config = new GatewayConfig(['public_key' => 'i000', 'private_key' => 'priv']);
    $sign = fn (string $data) => base64_encode(sha1('priv'.$data.'priv', true));

    it('signs the request and returns the payment page', function () use ($config) {
        Http::fake(['epoint.az/api/1/request' => Http::response(['status' => 'success', 'redirect_url' => 'https://epoint.az/pay/x', 'transaction' => 'te1'])]);

        $r = (new EpointGateway)->checkout(lpInvoice('AZN', 12.5), $config, 'https://ok', 'https://fail', 'https://hook');

        expect($r->redirectUrl)->toBe('https://epoint.az/pay/x');
        Http::assertSent(function ($q) {
            $payload = json_decode(base64_decode($q['data']), true);

            return $payload['order_id'] === 'INV-2026-000007' && $payload['amount'] == 12.5 && $q['signature'] === base64_encode(sha1('priv'.$q['data'].'priv', true));
        });
    });

    it('trusts only a correctly signed callback', function () use ($config, $sign) {
        $gw = new EpointGateway;
        $data = base64_encode(json_encode(['order_id' => 'INV-2026-000007', 'status' => 'success', 'transaction' => 'te1', 'amount' => 12.5]));

        $n = $gw->webhook(Request::create('/w', 'POST', ['data' => $data, 'signature' => $sign($data)]), $config);
        expect($n)->toMatchObject(['invoiceNumber' => 'INV-2026-000007', 'status' => 'succeeded', 'amount' => 1250, 'currency' => 'AZN']);
        expect(fn () => $gw->webhook(Request::create('/w', 'POST', ['data' => $data, 'signature' => 'nope']), $config))->toThrow(GatewayException::class);
    });

    it('confirms a return by asking Epoint, and only supports AZN', function () use ($config) {
        Http::fake(['epoint.az/api/1/get-status' => Http::response(['status' => 'success', 'transaction' => 'te1'])]);
        $n = (new EpointGateway)->confirmReturn(Request::create('/r'), lpInvoice('AZN', 12.5), $config);
        expect($n->status)->toBe('succeeded')->and($n->amount)->toBe(1250)->and((new EpointGateway)->supportsCurrency('USD'))->toBeFalse();
    });
});

describe('local SMS', function () {
    it('sends through Netgsm and notices a rejected code', function () {
        $cfg = ['usercode' => '850', 'password' => 'pw', 'header' => 'MYSHOP'];
        Http::fake(['api.netgsm.com.tr/*' => Http::response(['code' => '00', 'jobid' => '1'])]);
        (new NetgsmProvider)->send('sms', '+90 532 111 22 33', 'Hello', $cfg);
        Http::assertSent(fn ($q) => $q['msgheader'] === 'MYSHOP' && $q['messages'][0]['no'] === '905321112233' && $q->hasHeader('Authorization'));

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.netgsm.com.tr/*' => Http::response(['code' => '30'])]);
        expect(fn () => (new NetgsmProvider)->send('sms', '+905321112233', 'x', $cfg))->toThrow(MessagingException::class);
    });

    it('sends through İleti Merkezi with the HMAC hash', function () {
        $cfg = ['public_key' => 'pub', 'secret_key' => 'sec', 'sender' => 'MYSHOP'];
        Http::fake(['api.iletimerkezi.com/*' => Http::response(['response' => ['status' => ['code' => 200, 'message' => 'OK']]])]);
        (new IletiMerkeziProvider)->send('sms', '+905321112233', 'Hello', $cfg);
        Http::assertSent(fn ($q) => $q['request']['authentication']['hash'] === hash_hmac('sha256', 'pub', 'sec') && $q['request']['order']['message']['receipents']['number'] === ['905321112233']);

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.iletimerkezi.com/*' => Http::response(['response' => ['status' => ['code' => 401, 'message' => 'bad']]])]);
        expect(fn () => (new IletiMerkeziProvider)->send('sms', '+905321112233', 'x', $cfg))->toThrow(MessagingException::class);
    });
});
