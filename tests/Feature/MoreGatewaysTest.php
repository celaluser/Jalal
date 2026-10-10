<?php

use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Gateways\MercadoPagoGateway;
use App\Modules\Billing\Gateways\MidtransGateway;
use App\Modules\Billing\Gateways\PaddleGateway;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Payments\GatewayConfig;
use App\Modules\Billing\Payments\GatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function mgInvoice(string $currency, float $total = 100): Invoice
{
    return new Invoice(['number' => 'INV-77', 'currency_code' => $currency, 'total' => $total, 'items' => [['description' => 'Pro (monthly)']], 'billing' => ['buyer' => ['email' => 'o@example.com']]]);
}

it('registers the three new gateways with their settings fields', function () {
    $all = app(GatewayManager::class)->all();
    expect($all)->toHaveKeys(['paddle', 'mercadopago', 'midtrans']);
    foreach (['paddle', 'mercadopago', 'midtrans'] as $code) {
        expect($all[$code]->fields())->not->toBeEmpty()->and($all[$code]->name())->not->toBe('');
    }
});

describe('midtrans', function () {
    it('only supports rupiah and creates a Snap payment', function () {
        $gw = new MidtransGateway;
        expect($gw->supportsCurrency('IDR'))->toBeTrue()->and($gw->supportsCurrency('USD'))->toBeFalse();
        Http::fake(['app.sandbox.midtrans.com/snap/v1/transactions' => Http::response(['token' => 'tok', 'redirect_url' => 'https://pay.example/snap'])]);

        $r = $gw->checkout(mgInvoice('IDR', 150000), new GatewayConfig(['server_key' => 'SB-key', 'environment' => 'sandbox']), 'https://r', 'https://c', 'https://w');
        expect($r->redirectUrl)->toBe('https://pay.example/snap');
        Http::assertSent(fn ($q) => $q['transaction_details']['order_id'] === 'INV-77' && $q['transaction_details']['gross_amount'] === 150000 && $q->hasHeader('Authorization'));
    });

    it('checks the SHA-512 signature of a notification', function () {
        $gw = new MidtransGateway;
        $config = new GatewayConfig(['server_key' => 'key']);
        $body = ['order_id' => 'INV-77', 'status_code' => '200', 'gross_amount' => '150000.00', 'transaction_status' => 'settlement', 'transaction_id' => 't1'];
        $good = $body + ['signature_key' => hash('sha512', 'INV-77'.'200'.'150000.00'.'key')];

        $n = $gw->webhook(Request::create('/w', 'POST', [], [], [], [], json_encode($good)), $config);
        expect($n)->toMatchObject(['invoiceNumber' => 'INV-77', 'amount' => 15000000, 'currency' => 'IDR', 'status' => 'succeeded']);
        expect(fn () => $gw->webhook(Request::create('/w', 'POST', [], [], [], [], json_encode($body + ['signature_key' => 'forged'])), $config))->toThrow(GatewayException::class);

        $pending = ['transaction_status' => 'pending'] + $body;
        $pending['signature_key'] = $good['signature_key'];
        expect($gw->webhook(Request::create('/w', 'POST', [], [], [], [], json_encode($pending)), $config))->toBeNull();
        $denied = ['transaction_status' => 'expire'] + $body;
        $denied['signature_key'] = $good['signature_key'];
        expect($gw->webhook(Request::create('/w', 'POST', [], [], [], [], json_encode($denied)), $config)->status)->toBe('failed');
    });

    it('confirms the return by asking Midtrans', function () {
        Http::fake(['api.midtrans.com/v2/INV-77/status' => Http::response(['order_id' => 'INV-77', 'transaction_status' => 'capture', 'fraud_status' => 'accept', 'gross_amount' => '150000.00', 'transaction_id' => 't9'])]);
        $n = (new MidtransGateway)->confirmReturn(Request::create('/r'), mgInvoice('IDR', 150000), new GatewayConfig(['server_key' => 'k']));
        expect($n->status)->toBe('succeeded');
    });
});

describe('mercado pago', function () {
    it('creates a preference with the invoice as external reference', function () {
        Http::fake(['api.mercadopago.com/checkout/preferences' => Http::response(['id' => 'pref1', 'init_point' => 'https://mp.example/pay'])]);
        $r = (new MercadoPagoGateway)->checkout(mgInvoice('BRL', 49.9), new GatewayConfig(['access_token' => 'APP_USR-x']), 'https://r', 'https://c', 'https://w');

        expect($r->redirectUrl)->toBe('https://mp.example/pay');
        Http::assertSent(fn ($q) => $q['external_reference'] === 'INV-77' && $q['items'][0]['unit_price'] === 49.9 && $q['items'][0]['currency_id'] === 'BRL' && $q['notification_url'] === 'https://w');
    });

    it('trusts only the payment fetched from the API', function () {
        $gw = new MercadoPagoGateway;
        $config = new GatewayConfig(['access_token' => 'tok']);
        Http::fake(['api.mercadopago.com/v1/payments/123456' => Http::response(['status' => 'approved', 'external_reference' => 'INV-77', 'transaction_amount' => 49.9, 'currency_id' => 'BRL'])]);

        $n = $gw->webhook(Request::create('/w', 'POST', ['type' => 'payment', 'data' => ['id' => '123456']]), $config);
        expect($n)->toMatchObject(['invoiceNumber' => 'INV-77', 'amount' => 4990, 'currency' => 'BRL', 'status' => 'succeeded']);
        expect($gw->webhook(Request::create('/w', 'POST', ['type' => 'merchant_order', 'data' => ['id' => '1']]), $config))->toBeNull();
        expect(fn () => $gw->webhook(Request::create('/w', 'POST', ['type' => 'payment', 'data' => ['id' => '../x']]), $config))->toThrow(GatewayException::class);
    });

    it('waits on pending payments', function () {
        Http::fake(['api.mercadopago.com/v1/payments/999' => Http::response(['status' => 'in_process'])]);
        expect((new MercadoPagoGateway)->webhook(Request::create('/w', 'POST', ['type' => 'payment', 'data' => ['id' => '999']]), new GatewayConfig(['access_token' => 't'])))->toBeNull();
    });
});

describe('paddle', function () {
    function mgSigned(string $body, string $secret, ?int $ts = null): string
    {
        $ts ??= time();

        return "ts={$ts};h1=".hash_hmac('sha256', $ts.':'.$body, $secret);
    }

    it('creates a transaction in minor units', function () {
        Http::fake(['sandbox-api.paddle.com/transactions' => Http::response(['data' => ['id' => 'txn_01', 'checkout' => ['url' => 'https://pay.paddle.example/x']]])]);
        $r = (new PaddleGateway)->checkout(mgInvoice('USD', 49.5), new GatewayConfig(['api_key' => 'k', 'environment' => 'sandbox']), 'https://r', 'https://c', 'https://w');

        expect($r->redirectUrl)->toBe('https://pay.paddle.example/x');
        Http::assertSent(fn ($q) => $q['items'][0]['price']['unit_price']['amount'] === '4950' && $q['custom_data']['invoice'] === 'INV-77');
    });

    it('accepts only a correctly signed, fresh completed-transaction event', function () {
        $gw = new PaddleGateway;
        $config = new GatewayConfig(['webhook_secret' => 'pdl_secret']);
        $body = json_encode(['event_type' => 'transaction.completed', 'data' => ['id' => 'txn_9', 'status' => 'completed', 'currency_code' => 'USD', 'custom_data' => ['invoice' => 'INV-77'], 'details' => ['totals' => ['grand_total' => '4950']]]]);
        $req = fn (string $sig) => Request::create('/w', 'POST', [], [], [], ['HTTP_PADDLE_SIGNATURE' => $sig], $body);

        expect($gw->webhook($req(mgSigned($body, 'pdl_secret')), $config))->toMatchObject(['invoiceNumber' => 'INV-77', 'amount' => 4950, 'currency' => 'USD', 'status' => 'succeeded']);
        expect(fn () => $gw->webhook($req(mgSigned($body, 'wrong')), $config))->toThrow(GatewayException::class);
        expect(fn () => $gw->webhook($req(mgSigned($body, 'pdl_secret', time() - 3600)), $config))->toThrow(GatewayException::class);
        expect(fn () => $gw->webhook($req(''), $config))->toThrow(GatewayException::class);

        $other = json_encode(['event_type' => 'transaction.created', 'data' => []]);
        expect($gw->webhook(Request::create('/w', 'POST', [], [], [], ['HTTP_PADDLE_SIGNATURE' => mgSigned($other, 'pdl_secret')], $other), $config))->toBeNull();
    });
});
