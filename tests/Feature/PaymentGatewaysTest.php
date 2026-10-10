<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Gateways\FlutterwaveGateway;
use App\Modules\Billing\Gateways\IyzicoGateway;
use App\Modules\Billing\Gateways\MollieGateway;
use App\Modules\Billing\Gateways\PayPalGateway;
use App\Modules\Billing\Gateways\PaystackGateway;
use App\Modules\Billing\Gateways\RazorpayGateway;
use App\Modules\Billing\Gateways\StripeGateway;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Payments\GatewayConfig;
use App\Modules\Billing\Payments\GatewayManager;
use App\Modules\Billing\Payments\PaymentNotification;
use App\Modules\Billing\Services\CheckoutService;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Billing\Services\PaymentProcessor;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Billing\Support\Money;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

function gwPlan(array $o = []): Plan
{
    return Plan::create(array_merge(['name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD'], $o));
}

function gwRestaurant(?string $email = null): Restaurant
{
    $r = Restaurant::create(['name' => 'Buyer Bistro', 'slug' => 'buyer-'.uniqid()]);
    $r->update(['owner_id' => User::factory()->create(['restaurant_id' => $r->id, 'email' => $email ?? uniqid('owner').'@buyer.test'])->id]);

    return $r->refresh();
}

function gwInvoice(?Plan $plan = null, ?Restaurant $r = null): Invoice
{
    return app(InvoiceService::class)->create($r ?? gwRestaurant(), $plan ?? gwPlan());
}

function note(Invoice $invoice, string $gateway = 'stripe', string $tx = 'tx_1', ?int $amount = null, ?string $currency = null, string $status = 'succeeded'): PaymentNotification
{
    return new PaymentNotification($gateway, $invoice->number, $tx, $status, $amount ?? Money::toMinor($invoice->total, $invoice->currency_code), $currency ?? $invoice->currency_code);
}

describe('money', function () {
    it('uses the right currency exponent', function () {
        expect(Money::toMinor(10.5, 'USD'))->toBe(1050)
            ->and(Money::toMinor(1000, 'JPY'))->toBe(1000)
            ->and(Money::toMinor(1.234, 'KWD'))->toBe(1234)
            ->and(Money::decimalString(5, 'JPY'))->toBe('5')
            ->and(Money::decimalString(5, 'EUR'))->toBe('5.00');
    });
});

describe('payment processor', function () {
    beforeEach(fn () => $this->processor = app(PaymentProcessor::class));

    it('settles an open invoice and activates the plan', function () {
        $invoice = gwInvoice();
        $this->processor->handle(note($invoice));

        $invoice->refresh();
        expect($invoice->status)->toBe('paid')->and($invoice->gateway)->toBe('stripe')->and($invoice->subscription_id)->not->toBeNull();

        $sub = app(SubscriptionService::class)->current(Restaurant::find($invoice->restaurant_id));
        expect($sub->plan_id)->toBe($invoice->plan_id)->and($sub->status)->toBe('active')->and($sub->gateway)->toBe('stripe');
    });

    it('is idempotent: a replayed webhook neither duplicates the payment nor extends the plan twice', function () {
        $invoice = gwInvoice();
        $this->processor->handle(note($invoice));
        $end = app(SubscriptionService::class)->current(Restaurant::find($invoice->restaurant_id))->ends_at;

        $this->processor->handle(note($invoice));
        $this->processor->handle(note($invoice));

        expect(Payment::allTenants()->where('invoice_id', $invoice->id)->count())->toBe(1)
            ->and(app(SubscriptionService::class)->current(Restaurant::find($invoice->restaurant_id))->ends_at->equalTo($end))->toBeTrue();
    });

    it('refuses to activate when the paid amount or currency differs from the invoice', function (int $amount, string $currency) {
        $invoice = gwInvoice();
        $payment = $this->processor->handle(note($invoice, amount: $amount, currency: $currency));

        expect($payment->status)->toBe('mismatch')
            ->and($invoice->fresh()->status)->toBe('open')
            ->and(app(SubscriptionService::class)->current(Restaurant::find($invoice->restaurant_id)))->toBeNull();
    })->with([
        'underpaid' => [1, 'USD'],
        'overpaid' => [999999, 'USD'],
        'wrong currency' => [1000, 'EUR'],
    ]);

    it('records failures without touching the invoice and ignores unknown invoices', function () {
        $invoice = gwInvoice();

        expect($this->processor->handle(note($invoice, status: 'failed'))->status)->toBe('failed')
            ->and($invoice->fresh()->status)->toBe('open');

        $ghost = new PaymentNotification('stripe', 'INV-0000-000000', 'tx', 'succeeded', 1000, 'USD');
        expect($this->processor->handle($ghost))->toBeNull();
    });

    it('does not settle a void invoice even if money arrives', function () {
        $invoice = app(InvoiceService::class)->void(gwInvoice());
        $this->processor->handle(note($invoice));

        expect($invoice->fresh()->status)->toBe('void')
            ->and(app(SubscriptionService::class)->current(Restaurant::find($invoice->restaurant_id)))->toBeNull();
    });

    it('renews when paying again for the plan the restaurant is on and switches otherwise', function () {
        $r = gwRestaurant();
        $plan = gwPlan();
        $first = gwInvoice($plan, $r);
        $this->processor->handle(note($first, tx: 'a'));
        $sub = app(SubscriptionService::class)->current($r);
        $end = $sub->ends_at->copy();

        $this->processor->handle(note(gwInvoice($plan, $r), tx: 'b'));
        $renewed = app(SubscriptionService::class)->current($r);
        expect($renewed->id)->toBe($sub->id)->and($renewed->ends_at->gt($end))->toBeTrue();

        $other = gwPlan(['slug' => 'other']);
        $this->processor->handle(note(gwInvoice($other, $r), tx: 'c'));
        expect(app(SubscriptionService::class)->current($r)->plan_id)->toBe($other->id);
    });
});

describe('stripe', function () {
    beforeEach(function () {
        $this->gw = new StripeGateway;
        $this->config = new GatewayConfig(['secret_key' => 'sk_test', 'webhook_secret' => 'whsec_test']);
        $this->event = fn (Invoice $i, string $type = 'checkout.session.completed', string $paid = 'paid') => json_encode([
            'type' => $type,
            'data' => ['object' => ['id' => 'cs_1', 'client_reference_id' => $i->number, 'payment_status' => $paid, 'payment_intent' => 'pi_1', 'amount_total' => 1000, 'currency' => 'usd']],
        ]);
        $this->signed = function (string $body, ?int $time = null, string $secret = 'whsec_test') {
            $t = $time ?? time();

            return Request::create('/w', 'POST', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', "{$t}.{$body}", $secret)], $body);
        };
    });

    it('creates a checkout session with the invoice number and minor units', function () {
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1'])]);

        $result = $this->gw->checkout(gwInvoice(), $this->config, 'https://app.test/return', 'https://app.test/cancel', 'https://app.test/hook');

        expect($result->redirectUrl)->toBe('https://checkout.stripe.com/c/pay/cs_1')->and($result->reference)->toBe('cs_1');
        Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer sk_test')
            && $req['line_items'][0]['price_data']['unit_amount'] === 1000
            && $req['line_items'][0]['price_data']['currency'] === 'usd'
            && str_starts_with($req['client_reference_id'], 'INV-'));
    });

    it('accepts a correctly signed completed event', function () {
        $invoice = gwInvoice();
        $n = $this->gw->webhook(($this->signed)(($this->event)($invoice)), $this->config);

        expect($n)->toMatchObject(['invoiceNumber' => $invoice->number, 'transactionId' => 'pi_1', 'status' => 'succeeded', 'amount' => 1000, 'currency' => 'USD']);
    });

    it('rejects bad, missing, stale and wrong-secret signatures', function (callable $make) {
        $invoice = gwInvoice();
        expect(fn () => $this->gw->webhook($make(($this->event)($invoice), $this), $this->config))->toThrow(GatewayException::class);
    })->with([
        'wrong secret' => [fn ($body, $t) => ($t->signed)($body, null, 'whsec_other')],
        'stale timestamp' => [fn ($body, $t) => ($t->signed)($body, time() - 3600)],
        'missing header' => [fn ($body, $t) => Request::create('/w', 'POST', [], [], [], [], $body)],
        'tampered body' => [function ($body, $t) {
            $time = time();
            $sig = 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$body, 'whsec_test');

            return Request::create('/w', 'POST', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $sig], str_replace('1000', '1', $body));
        }],
    ]);

    it('ignores unpaid sessions and unrelated events, and reports async failures', function () {
        $invoice = gwInvoice();

        expect($this->gw->webhook(($this->signed)(($this->event)($invoice, paid: 'unpaid')), $this->config))->toBeNull()
            ->and($this->gw->webhook(($this->signed)(($this->event)($invoice, 'customer.created')), $this->config))->toBeNull()
            ->and($this->gw->webhook(($this->signed)(($this->event)($invoice, 'checkout.session.async_payment_failed')), $this->config)->status)->toBe('failed');
    });

    it('verifies the session with the API on return', function () {
        $invoice = gwInvoice();
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_1', 'client_reference_id' => $invoice->number, 'payment_status' => 'paid', 'payment_intent' => 'pi_1', 'amount_total' => 1000, 'currency' => 'usd'])]);

        expect($this->gw->confirmReturn(Request::create('/r?session_id=cs_1'), $invoice, $this->config)->status)->toBe('succeeded');
        expect($this->gw->confirmReturn(Request::create('/r?session_id=bad id'), $invoice, $this->config))->toBeNull();

        // A session that belongs to another invoice must not confirm this one.
        expect($this->gw->confirmReturn(Request::create('/r?session_id=cs_1'), gwInvoice(), $this->config))->toBeNull();
    });
});

describe('paystack', function () {
    it('verifies the HMAC-SHA512 signature and normalises charge.success', function () {
        $gw = new PaystackGateway;
        $config = new GatewayConfig(['secret_key' => 'sk_test']);
        $body = json_encode(['event' => 'charge.success', 'data' => ['id' => 99, 'reference' => 'INV-X', 'status' => 'success', 'amount' => 5000, 'currency' => 'NGN']]);
        $req = fn ($sig) => Request::create('/w', 'POST', [], [], [], ['HTTP_X_PAYSTACK_SIGNATURE' => $sig], $body);

        expect($gw->webhook($req(hash_hmac('sha512', $body, 'sk_test')), $config))->toMatchObject(['invoiceNumber' => 'INV-X', 'amount' => 5000, 'currency' => 'NGN', 'transactionId' => '99']);
        expect(fn () => $gw->webhook($req('nope'), $config))->toThrow(GatewayException::class);
        expect($gw->supportsCurrency('NGN'))->toBeTrue()->and($gw->supportsCurrency('EUR'))->toBeFalse();
    });

    it('initialises with the owner e-mail and verifies on return', function () {
        $gw = new PaystackGateway;
        $config = new GatewayConfig(['secret_key' => 'sk_test']);
        $invoice = gwInvoice(r: gwRestaurant('owner@buyer.test'));
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response(['data' => ['authorization_url' => 'https://checkout.paystack.com/x', 'reference' => $invoice->number]]),
            'api.paystack.co/transaction/verify/*' => Http::response(['data' => ['id' => 7, 'reference' => $invoice->number, 'status' => 'success', 'amount' => 1000, 'currency' => 'USD']]),
        ]);

        expect($gw->checkout($invoice, $config, 'r', 'c', 'w')->redirectUrl)->toBe('https://checkout.paystack.com/x');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'initialize') && $r['email'] === 'owner@buyer.test' && $r['amount'] === 1000);
        expect($gw->confirmReturn(Request::create('/r'), $invoice, $config)->amount)->toBe(1000);
    });
});

describe('flutterwave', function () {
    it('authenticates by hash header and re-fetches the transaction before trusting it', function () {
        $gw = new FlutterwaveGateway;
        $config = new GatewayConfig(['secret_key' => 'FLWSECK', 'secret_hash' => 'hash123']);
        $body = json_encode(['event' => 'charge.completed', 'data' => ['id' => 55, 'status' => 'successful', 'amount' => 1, 'tx_ref' => 'FORGED']]);
        Http::fake(['api.flutterwave.com/v3/transactions/55/verify' => Http::response(['data' => ['status' => 'successful', 'tx_ref' => 'INV-REAL', 'amount' => 10, 'currency' => 'USD', 'flw_ref' => 'f']])]);

        $n = $gw->webhook(Request::create('/w', 'POST', [], [], [], ['HTTP_VERIF_HASH' => 'hash123', 'CONTENT_TYPE' => 'application/json'], $body), $config);

        // Amount and invoice come from the verified API response, not from the webhook body.
        expect($n)->toMatchObject(['invoiceNumber' => 'INV-REAL', 'amount' => 1000, 'transactionId' => '55']);
        expect(fn () => $gw->webhook(Request::create('/w', 'POST', [], [], [], ['HTTP_VERIF_HASH' => 'wrong'], $body), $config))->toThrow(GatewayException::class);
        expect(fn () => $gw->webhook(Request::create('/w', 'POST', [], [], [], [], $body), $config))->toThrow(GatewayException::class);
    });

    it('does not confirm a return whose transaction belongs to another invoice', function () {
        $gw = new FlutterwaveGateway;
        $invoice = gwInvoice();
        Http::fake(['api.flutterwave.com/*' => Http::response(['data' => ['status' => 'successful', 'tx_ref' => 'INV-OTHER', 'amount' => 10, 'currency' => 'USD']])]);

        expect($gw->confirmReturn(Request::create('/r?transaction_id=1&status=successful'), $invoice, new GatewayConfig(['secret_key' => 'k'])))->toBeNull();
    });
});

describe('razorpay', function () {
    it('verifies the webhook signature and the signed return callback', function () {
        $gw = new RazorpayGateway;
        $config = new GatewayConfig(['key_id' => 'rzp', 'key_secret' => 'secret', 'webhook_secret' => 'whsec']);
        $body = json_encode(['event' => 'payment_link.paid', 'payload' => ['payment_link' => ['entity' => ['id' => 'plink_1', 'reference_id' => 'INV-1', 'status' => 'paid', 'amount_paid' => 100000, 'currency' => 'INR']], 'payment' => ['entity' => ['id' => 'pay_1']]]]);
        $req = fn ($sig) => Request::create('/w', 'POST', [], [], [], ['HTTP_X_RAZORPAY_SIGNATURE' => $sig], $body);

        expect($gw->webhook($req(hash_hmac('sha256', $body, 'whsec')), $config))->toMatchObject(['invoiceNumber' => 'INV-1', 'transactionId' => 'pay_1', 'amount' => 100000]);
        expect(fn () => $gw->webhook($req('bad'), $config))->toThrow(GatewayException::class);

        $invoice = gwInvoice();
        Http::fake(['api.razorpay.com/v1/payment_links/*' => Http::response(['id' => 'plink_1', 'reference_id' => $invoice->number, 'status' => 'paid', 'amount_paid' => 1000, 'currency' => 'USD'])]);
        $query = ['razorpay_payment_link_id' => 'plink_1', 'razorpay_payment_link_reference_id' => $invoice->number, 'razorpay_payment_link_status' => 'paid', 'razorpay_payment_id' => 'pay_1'];
        $good = $query + ['razorpay_signature' => hash_hmac('sha256', 'plink_1|'.$invoice->number.'|paid|pay_1', 'secret')];

        expect($gw->confirmReturn(Request::create('/r', 'GET', $good), $invoice, $config)->status)->toBe('succeeded');
        expect($gw->confirmReturn(Request::create('/r', 'GET', $query + ['razorpay_signature' => 'forged']), $invoice, $config))->toBeNull();
    });
});

describe('mollie', function () {
    it('trusts only the API response, never the webhook body', function () {
        $gw = new MollieGateway;
        $config = new GatewayConfig(['api_key' => 'test_key']);
        Http::fake(['api.mollie.com/v2/payments/tr_abc' => Http::response(['id' => 'tr_abc', 'status' => 'paid', 'amount' => ['currency' => 'EUR', 'value' => '10.00'], 'metadata' => ['invoice' => 'INV-9']])]);

        $n = $gw->webhook(Request::create('/w', 'POST', ['id' => 'tr_abc']), $config);
        expect($n)->toMatchObject(['invoiceNumber' => 'INV-9', 'amount' => 1000, 'currency' => 'EUR', 'status' => 'succeeded']);
        expect(fn () => $gw->webhook(Request::create('/w', 'POST', ['id' => '../evil']), $config))->toThrow(GatewayException::class);
    });

    it('waits on open payments and reports canceled ones as failed', function () {
        $gw = new MollieGateway;
        $config = new GatewayConfig(['api_key' => 'k']);
        Http::fake(['api.mollie.com/v2/payments/tr_open' => Http::response(['status' => 'open']), 'api.mollie.com/v2/payments/tr_off' => Http::response(['status' => 'canceled', 'amount' => ['currency' => 'EUR', 'value' => '1.00'], 'metadata' => ['invoice' => 'INV-1']])]);

        expect($gw->webhook(Request::create('/w', 'POST', ['id' => 'tr_open']), $config))->toBeNull()
            ->and($gw->webhook(Request::create('/w', 'POST', ['id' => 'tr_off']), $config)->status)->toBe('failed');
    });
});

describe('paypal', function () {
    beforeEach(function () {
        $this->gw = new PayPalGateway;
        $this->config = new GatewayConfig(['mode' => 'test', 'client_id' => 'id', 'client_secret' => 'sec']);
    });

    it('creates an order and only captures the order created for the invoice', function () {
        $invoice = gwInvoice();
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => 'ORDER123456', 'links' => [['rel' => 'payer-action', 'href' => 'https://paypal.test/approve']]]),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER123456/capture' => Http::response(['status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP1', 'status' => 'COMPLETED', 'invoice_id' => $invoice->number, 'amount' => ['value' => '10.00', 'currency_code' => 'USD']]]]]]]),
        ]);

        $result = $this->gw->checkout($invoice, $this->config, 'r', 'c', 'w');
        expect($result->redirectUrl)->toBe('https://paypal.test/approve')->and($result->reference)->toBe('ORDER123456');
        Payment::allTenants()->create(['restaurant_id' => $invoice->restaurant_id, 'invoice_id' => $invoice->id, 'gateway' => 'paypal', 'transaction_id' => 'ORDER123456', 'amount' => 1000, 'currency_code' => 'USD', 'status' => 'pending']);

        expect($this->gw->confirmReturn(Request::create('/r?token=ORDER123456'), $invoice, $this->config))->toMatchObject(['transactionId' => 'CAP1', 'amount' => 1000, 'invoiceNumber' => $invoice->number]);
        // An order id we never issued for this invoice is ignored.
        expect($this->gw->confirmReturn(Request::create('/r?token=SOMEOTHERORDER'), $invoice, $this->config))->toBeNull();
    });

    it('does nothing with webhooks unless a webhook id is configured, and rejects failed verification', function () {
        expect($this->gw->webhook(Request::create('/w', 'POST'), $this->config))->toBeNull();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'FAILURE']),
        ]);
        $config = new GatewayConfig(['mode' => 'test', 'client_id' => 'id', 'client_secret' => 'sec', 'webhook_id' => 'WH1']);

        expect(fn () => $this->gw->webhook(Request::create('/w', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"event_type":"PAYMENT.CAPTURE.COMPLETED"}'), $config))->toThrow(GatewayException::class);
    });
});

describe('iyzico', function () {
    beforeEach(function () {
        $this->gw = new IyzicoGateway;
        $this->config = new GatewayConfig(['mode' => 'test', 'api_key' => 'api', 'secret_key' => 'secret']);
    });

    it('signs requests with IYZWSv2 and returns the payment page', function () {
        $invoice = gwInvoice(gwPlan(['currency_code' => 'TRY', 'price' => 99.9]));
        Http::fake(['sandbox-api.iyzipay.com/*' => Http::response(['status' => 'success', 'token' => 'tok-12345678', 'paymentPageUrl' => 'https://iyzico.test/pay'])]);

        $result = $this->gw->checkout($invoice, $this->config, 'https://app.test/return', 'c', 'w');

        expect($result->redirectUrl)->toBe('https://iyzico.test/pay');
        Http::assertSent(function ($req) {
            $auth = (string) $req->header('Authorization')[0];
            $decoded = base64_decode(substr($auth, strlen('IYZWSv2 ')));
            parse_str(str_replace(['apiKey:', 'randomKey:', 'signature:'], ['apiKey=', 'randomKey=', 'signature='], $decoded), $parts);
            $expected = hash_hmac('sha256', $parts['randomKey'].'/payment/iyzipos/checkoutform/initialize/auth/ecom'.$req->body(), 'secret');

            return str_starts_with($auth, 'IYZWSv2 ') && hash_equals($expected, $parts['signature']) && $req['price'] === '99.90';
        });
    });

    it('confirms a returned token only if iyzico says SUCCESS for this invoice', function () {
        $invoice = gwInvoice();
        Http::fake(['sandbox-api.iyzipay.com/*' => Http::sequence()
            ->push(['status' => 'success', 'paymentStatus' => 'SUCCESS', 'basketId' => $invoice->number, 'paidPrice' => 10, 'currency' => 'USD', 'paymentId' => 'P1'])
            ->push(['status' => 'success', 'paymentStatus' => 'FAILURE', 'basketId' => $invoice->number])
            ->push(['status' => 'success', 'paymentStatus' => 'SUCCESS', 'basketId' => 'INV-OTHER', 'paidPrice' => 10, 'currency' => 'USD', 'paymentId' => 'P2'])]);

        $ok = $this->gw->confirmReturn(Request::create('/r', 'POST', ['token' => 'abcdef-123456']), $invoice, $this->config);
        expect($ok)->toMatchObject(['amount' => 1000, 'transactionId' => 'P1', 'status' => 'succeeded'])
            ->and($this->gw->confirmReturn(Request::create('/r', 'POST', ['token' => 'abcdef-123456']), $invoice, $this->config))->toBeNull()
            ->and($this->gw->confirmReturn(Request::create('/r', 'POST', ['token' => 'abcdef-123456']), $invoice, $this->config))->toBeNull()
            ->and($this->gw->confirmReturn(Request::create('/r', 'POST', ['token' => 'bad token!']), $invoice, $this->config))->toBeNull();
    });

    it('surfaces business failures reported with HTTP 200', function () {
        Http::fake(['sandbox-api.iyzipay.com/*' => Http::response(['status' => 'failure', 'errorMessage' => 'invalid signature'])]);

        expect(fn () => $this->gw->checkout(gwInvoice(), $this->config, 'r', 'c', 'w'))->toThrow(GatewayException::class, 'invalid signature');
    });
});

describe('manager and checkout', function () {
    beforeEach(function () {
        $this->manager = app(GatewayManager::class);
        $this->checkout = app(CheckoutService::class);
    });

    it('is not ready until enabled and every required field is set; optional fields do not block', function () {
        expect($this->manager->isReady('paypal'))->toBeFalse();

        $this->manager->save('paypal', true, ['mode' => 'test', 'client_id' => 'id']);
        expect($this->manager->isReady('paypal'))->toBeFalse();

        $this->manager->save('paypal', true, ['client_secret' => 's3cret']);
        expect($this->manager->isReady('paypal'))->toBeTrue();

        $this->manager->save('paypal', false, []);
        expect($this->manager->isReady('paypal'))->toBeFalse();
    });

    it('stores secrets encrypted and keeps them when the field is left blank', function () {
        $this->manager->save('stripe', true, ['secret_key' => 'sk_live_abc', 'webhook_secret' => 'whsec_1']);
        $this->manager->save('stripe', true, ['secret_key' => '', 'webhook_secret' => '']);

        expect($this->manager->config('stripe')->get('secret_key'))->toBe('sk_live_abc')
            ->and(DB::table('settings')->where('key', 'payments.stripe.secret_key')->value('value'))->not->toContain('sk_live_abc');
    });

    it('offers only ready gateways that support the currency', function () {
        $this->manager->save('paystack', true, ['secret_key' => 'sk']);
        $this->manager->save('bank_transfer', true, ['instructions' => 'IBAN']);

        expect(array_keys($this->manager->availableFor('USD')))->toEqualCanonicalizing(['paystack', 'bank_transfer'])
            ->and(array_keys($this->manager->availableFor('EUR')))->toBe(['bank_transfer']);
    });

    it('starts a bank-transfer checkout: open invoice, pending payment, instructions with the invoice number', function () {
        $this->manager->save('bank_transfer', true, ['instructions' => 'IBAN TR00. Reference: :reference']);
        $r = gwRestaurant();

        ['invoice' => $invoice, 'result' => $result] = $this->checkout->start($r, gwPlan(), 'bank_transfer');

        expect($invoice->status)->toBe('open')->and($result->redirectUrl)->toBeNull()
            ->and($result->instructions)->toBe('IBAN TR00. Reference: '.$invoice->number)
            ->and(Payment::allTenants()->where('invoice_id', $invoice->id)->value('status'))->toBe('pending')
            ->and(app(SubscriptionService::class)->current($r))->toBeNull();
    });

    it('voids the invoice when the gateway is unavailable or fails', function () {
        $r = gwRestaurant();
        expect(fn () => $this->checkout->start($r, gwPlan(), 'stripe'))->toThrow(BillingException::class);
        expect(Invoice::allTenants()->where('restaurant_id', $r->id)->value('status'))->toBe('void');

        $this->manager->save('stripe', true, ['secret_key' => 'sk', 'webhook_secret' => 'wh']);
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'bad key']], 401)]);
        $r2 = gwRestaurant();
        expect(fn () => $this->checkout->start($r2, gwPlan(), 'stripe'))->toThrow(BillingException::class);
        expect(Invoice::allTenants()->where('restaurant_id', $r2->id)->value('status'))->toBe('void');
    });

    it('activates immediately when nothing has to be paid (free plan or full discount)', function () {
        $r = gwRestaurant();
        $free = gwPlan(['interval' => 'free', 'price' => 0]);
        $this->checkout->start($r, $free, 'stripe');
        expect(app(SubscriptionService::class)->current($r)->plan_id)->toBe($free->id);

        $r2 = gwRestaurant();
        $coupon = Coupon::create(['code' => 'FREE100', 'type' => 'percent', 'value' => 100]);
        $paid = gwPlan(['price' => 30]);
        $this->checkout->start($r2, $paid, 'stripe', 'free100');
        expect(app(SubscriptionService::class)->current($r2)->plan_id)->toBe($paid->id);
    });

    it('rejects unusable coupons before an invoice exists', function () {
        $r = gwRestaurant();
        Coupon::create(['code' => 'OLD', 'type' => 'percent', 'value' => 10, 'expires_at' => now()->subDay()]);

        expect(fn () => $this->checkout->start($r, gwPlan(), 'bank_transfer', 'OLD'))->toThrow(BillingException::class);
        expect(Invoice::allTenants()->where('restaurant_id', $r->id)->count())->toBe(0);
    });
});

describe('http endpoints', function () {
    beforeEach(function () {
        $this->manager = app(GatewayManager::class);
        $this->manager->save('stripe', true, ['secret_key' => 'sk_test', 'webhook_secret' => 'whsec_test']);
    });

    it('books a payment from a correctly signed Stripe webhook end to end', function () {
        $invoice = gwInvoice();
        $body = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_1', 'client_reference_id' => $invoice->number, 'payment_status' => 'paid', 'payment_intent' => 'pi_9', 'amount_total' => 1000, 'currency' => 'usd']]]);
        $t = time();

        $this->call('POST', '/webhooks/payments/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', "{$t}.{$body}", 'whsec_test'), 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        expect($invoice->fresh()->status)->toBe('paid');
    });

    it('answers 400 to forged webhooks and 404 to unknown or disabled gateways, without booking anything', function () {
        $invoice = gwInvoice();

        $this->postJson('/webhooks/payments/stripe', ['type' => 'checkout.session.completed'])->assertStatus(400);
        $this->postJson('/webhooks/payments/nope')->assertNotFound();
        $this->manager->save('stripe', false, []);
        $this->postJson('/webhooks/payments/stripe')->assertNotFound();

        expect($invoice->fresh()->status)->toBe('open');
    });

    it('needs no CSRF token or session for webhooks', function () {
        $this->withMiddleware();
        $this->postJson('/webhooks/payments/stripe', [])->assertStatus(400); // reaches the handler, not a 419
    });

    it('confirms the return by asking the gateway and never trusts the query string', function () {
        $invoice = gwInvoice();
        Http::fake(['api.stripe.com/*' => Http::sequence()
            ->push(['id' => 'cs_1', 'client_reference_id' => $invoice->number, 'payment_status' => 'unpaid'])
            ->push(['id' => 'cs_1', 'client_reference_id' => $invoice->number, 'payment_status' => 'paid', 'payment_intent' => 'pi_1', 'amount_total' => 1000, 'currency' => 'usd'])]);

        $this->get("/billing/return/stripe/{$invoice->number}?session_id=cs_1&status=paid&paid=1")->assertRedirect(route('billing.index'));
        expect($invoice->fresh()->status)->toBe('open');

        $this->get("/billing/return/stripe/{$invoice->number}?session_id=cs_1");
        expect($invoice->fresh()->status)->toBe('paid');
    });

    it('accepts the cross-site POST iyzico sends on return', function () {
        $this->manager->save('iyzico', true, ['mode' => 'test', 'api_key' => 'a', 'secret_key' => 's']);
        $invoice = gwInvoice(gwPlan(['currency_code' => 'TRY', 'price' => 100]));
        Http::fake(['sandbox-api.iyzipay.com/*' => Http::response(['status' => 'success', 'paymentStatus' => 'SUCCESS', 'basketId' => $invoice->number, 'paidPrice' => 100, 'currency' => 'TRY', 'paymentId' => 'P1'])]);

        $this->post("/billing/return/iyzico/{$invoice->number}", ['token' => 'abcdef-123456'])->assertRedirect(route('billing.index'));

        expect($invoice->fresh()->status)->toBe('paid');
    });
});

describe('admin: payment settings and bank transfer approval', function () {
    beforeEach(function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
        $this->admin->assignRole(Permissions::SUPER_ADMIN);
    });

    it('lists every gateway with its webhook URL and never echoes stored secrets', function () {
        app(GatewayManager::class)->save('stripe', true, ['secret_key' => 'sk_live_topsecret', 'webhook_secret' => 'whsec_topsecret']);

        $page = $this->actingAs($this->admin)->get('/admin/settings/payments')->assertOk()
            ->assertSee(route('webhooks.payments', 'stripe'))->assertSee('Mollie')->assertSee('iyzico')->assertSee('Razorpay');

        expect($page->getContent())->not->toContain('sk_live_topsecret')->not->toContain('whsec_topsecret');
    });

    it('saves a gateway and validates select fields', function () {
        $this->actingAs($this->admin)->put('/admin/settings/payments/paypal', ['enabled' => '1', 'mode' => 'live', 'client_id' => 'cid', 'client_secret' => 'sec'])->assertSessionHasNoErrors();
        expect(app(GatewayManager::class)->isReady('paypal'))->toBeTrue()->and(app(GatewayManager::class)->config('paypal')->isLive())->toBeTrue();

        $this->put('/admin/settings/payments/paypal', ['enabled' => '1', 'mode' => 'chaos'])->assertSessionHasErrors('mode');
        $this->put('/admin/settings/payments/unknown', [])->assertNotFound();
    });

    it('activates the plan when the admin confirms a bank transfer', function () {
        app(GatewayManager::class)->save('bank_transfer', true, ['instructions' => 'IBAN']);
        $r = gwRestaurant();
        ['invoice' => $invoice] = app(CheckoutService::class)->start($r, gwPlan(), 'bank_transfer');

        $this->actingAs($this->admin)->post("/admin/invoices/{$invoice->id}/paid")->assertRedirect();

        expect($invoice->fresh()->status)->toBe('paid')
            ->and(app(SubscriptionService::class)->current($r)?->plan_id)->toBe($invoice->plan_id);
    });

    it('is closed to restaurant staff', function () {
        $r = gwRestaurant();
        $this->actingAs($r->owner)->get('/admin/settings/payments')->assertForbidden();
    });
});
