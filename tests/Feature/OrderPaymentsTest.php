<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderPayment;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\OrderSettings;
use App\Modules\Orders\Services\PaymentLedger;
use App\Modules\Orders\Services\RestaurantGateways;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    Cache::flush();
});

function opShop(bool $online = true, string $role = Permissions::OWNER): array
{
    $r = Restaurant::create(['name' => 'Pay Bistro', 'slug' => 'op'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now()]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => ['online_payments' => $online]]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $user = User::factory()->create(['restaurant_id' => $r->id]);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($r->id);
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $d = app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Food'], 'sort' => 1]);

        return ['pizza' => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Pizza'], 'price' => 20, 'sort' => 1]), 't1' => DiningTable::create(['name' => 'T1'])];
    });

    return [$r, $user, $d];
}

function opStripe(Restaurant $r): void
{
    app(RestaurantGateways::class)->save($r, 'stripe', true, ['secret_key' => 'sk_test_123', 'webhook_secret' => 'whsec_test']);
}

function opOrder(Restaurant $r, array $d, array $extra = [], int $qty = 1): Order
{
    return app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, $extra + ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'customer_email' => 'g@example.com', 'lines' => [['product_id' => $d['pizza']->id, 'qty' => $qty]]]));
}

function opFresh(Restaurant $r, Order $o): Order
{
    return app(TenantContext::class)->runAs($r, fn () => Order::find($o->id));
}

function opWebhook($test, Restaurant $r, string $reference, int $amount, string $secret = 'whsec_test', string $txn = 'pi_1')
{
    $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_1', 'payment_status' => 'paid', 'client_reference_id' => $reference, 'payment_intent' => $txn, 'amount_total' => $amount, 'currency' => 'usd']]]);
    $t = time();
    $sig = hash_hmac('sha256', $t.'.'.$payload, $secret);

    return $test->call('POST', '/r/'.$r->slug.'/pay/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$sig}"], $payload);
}

it('closes the bill only when the payments cover it, and counts tips apart', function () {
    [$r, , $d] = opShop();
    $order = opOrder($r, $d); // $20.00
    $ledger = app(PaymentLedger::class);
    app(TenantContext::class)->runAs($r, function () use ($ledger, $order) {
        $ledger->record($order, 'cash', 800, 100);
        $o = Order::find($order->id);
        expect($o->paid_cents)->toBe(800)->and($o->tip_cents)->toBe(100)->and($o->paid_at)->toBeNull()->and($ledger->remaining($o))->toBe(1200);
        $ledger->record($o, 'card', 5000); // more than owed: only what is left counts
        $o = Order::find($order->id);
        expect($o->paid_cents)->toBe(2000)->and($o->paid_at)->not->toBeNull()->and($o->payments)->toHaveCount(2);
        expect(fn () => $ledger->record($o, 'cash', 100))->toThrow(OrderException::class);
    });
});

it('refuses payments for cancelled orders and unknown methods', function () {
    [$r, , $d] = opShop();
    $order = opOrder($r, $d);
    app(TenantContext::class)->runAs($r, function () use ($order) {
        expect(fn () => app(PaymentLedger::class)->record($order, 'bitcoin', 100))->toThrow(OrderException::class);
        expect(fn () => app(PaymentLedger::class)->record($order, 'cash', 0))->toThrow(OrderException::class);
        Order::find($order->id)->forceFill(['status' => 'cancelled'])->save();
        expect(fn () => app(PaymentLedger::class)->record(Order::find($order->id), 'cash'))->toThrow(OrderException::class);
    });
});

it('lets staff take part payments with a tip and still pay the rest in one go', function () {
    [$r, $owner, $d] = opShop();
    $order = opOrder($r, $d);
    $this->actingAs($owner)->post(route('orders.payments.add', $order->id), ['method' => 'cash', 'amount' => '5', 'tip' => '1'])->assertRedirect();
    $o = opFresh($r, $order);
    expect($o->paid_cents)->toBe(500)->and($o->tip_cents)->toBe(100)->and($o->isPaid())->toBeFalse();
    $this->actingAs($owner)->get(route('orders.show', $order->id))->assertOk()->assertSee(__('orders.take_payment'))->assertSee('$15.00');

    $this->actingAs($owner)->postJson(route('orders.pay', $order->id), ['method' => 'card'])->assertOk(); // the quick button pays what is left
    $o = opFresh($r, $order);
    expect($o->paid_cents)->toBe(2000)->and($o->isPaid())->toBeTrue();
    $this->actingAs($owner)->post(route('orders.payments.add', $order->id), ['method' => 'cash', 'amount' => '1'])->assertSessionHasErrors('order');
});

it('keeps payments to staff who may take them', function () {
    [$r, $kitchen, $d] = opShop(true, Permissions::KITCHEN);
    $order = opOrder($r, $d);
    $this->actingAs($kitchen)->post(route('orders.payments.add', $order->id), ['method' => 'cash'])->assertForbidden();
    [, $otherOwner] = opShop();
    $this->actingAs($otherOwner)->post(route('orders.payments.add', $order->id), ['method' => 'cash'])->assertNotFound();
});

it('refunds cash by hand and reopens the bill', function () {
    [$r, $owner, $d] = opShop();
    $order = opOrder($r, $d);
    $payment = app(TenantContext::class)->runAs($r, fn () => app(PaymentLedger::class)->record($order, 'cash', null, 200, null));
    $this->actingAs($owner)->post(route('orders.payments.refund', $payment->id), ['amount' => '5', 'reason' => 'Cold pizza'])->assertRedirect()->assertSessionHas('status', __('orders.refunded_manually'));
    $o = opFresh($r, $order);
    expect($o->paid_cents)->toBe(1500)->and($o->refunded_cents)->toBe(500)->and($o->paid_at)->toBeNull();
    $this->actingAs($owner)->post(route('orders.payments.refund', $payment->id), ['amount' => '500'])->assertRedirect(); // capped at what is left
    expect(opFresh($r, $order)->refunded_cents)->toBe(2200)->and($payment->fresh()->refundable())->toBe(0);
    $this->actingAs($owner)->post(route('orders.payments.refund', $payment->id), [])->assertSessionHasErrors('order');
});

it('connects a gateway only when the plan allows it, and never shows secrets back', function () {
    [$r, $owner] = opShop(false);
    $this->actingAs($owner)->get(route('payments.settings'))->assertOk()->assertSee(__('orders.gateways_locked'));
    $this->actingAs($owner)->put(route('payments.settings.update', 'stripe'), ['enabled' => 1, 'secret_key' => 'sk_x'])->assertForbidden();

    [$r2, $owner2] = opShop(true);
    $this->actingAs($owner2)->put(route('payments.settings.update', 'stripe'), ['enabled' => 1, 'secret_key' => 'sk_test_ABCDEF', 'webhook_secret' => 'whsec_ABCDEF'])->assertRedirect();
    $page = $this->actingAs($owner2)->get(route('payments.settings'))->assertOk()->getContent();
    expect($page)->not->toContain('sk_test_ABCDEF')->and($page)->toContain('/pay/stripe/webhook');
    expect(app(RestaurantGateways::class)->ready($r2, 'stripe'))->toBeTrue();
    // A blank secret keeps the saved one.
    $this->actingAs($owner2)->put(route('payments.settings.update', 'stripe'), ['enabled' => 1, 'secret_key' => '', 'webhook_secret' => ''])->assertRedirect();
    expect(app(RestaurantGateways::class)->config($r2, 'stripe')->get('secret_key'))->toBe('sk_test_ABCDEF');
    $this->actingAs($owner2)->put(route('payments.settings.update', 'bank_transfer'), ['enabled' => 1])->assertNotFound();
});

it('offers online payment at checkout only when a gateway is ready', function () {
    [$r, , $d] = opShop();
    expect(app(OrderSettings::class)->paymentMethods($r))->toBe(['cash', 'card']);
    opStripe($r);
    expect(app(OrderSettings::class)->paymentMethods($r->fresh()))->toBe(['cash', 'card', 'online']);

    $res = $this->postJson('/r/'.$r->slug.'/order', ['type' => 'takeaway', 'payment_method' => 'online', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => [['product_id' => $d['pizza']->id, 'qty' => 1]]])->assertCreated();
    expect($res->json('url'))->toEndWith('?pay=1');

    app(SettingsService::class)->set('guest_payments.allowed.stripe', '0');
    expect(app(OrderSettings::class)->paymentMethods($r->fresh()))->toBe(['cash', 'card']);
});

it('sends the guest to the gateway for the bill plus a tip', function () {
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.test/c/cs_test_1'])]);
    [$r, , $d] = opShop();
    opStripe($r);
    $order = opOrder($r, $d);
    $res = $this->postJson('/r/'.$r->slug.'/order/'.$order->token.'/pay', ['gateway' => 'stripe', 'mode' => 'full', 'tip_percent' => 10])->assertOk();
    expect($res->json('url'))->toBe('https://checkout.stripe.test/c/cs_test_1');

    $row = app(TenantContext::class)->runAs($r, fn () => OrderPayment::first());
    expect([$row->status, $row->amount_cents, $row->tip_cents, $row->gateway])->toBe(['pending', 2000, 200, 'stripe']);
    Http::assertSent(fn ($req) => str_contains($req->url(), 'checkout/sessions') && $req['line_items'][0]['price_data']['unit_amount'] === 2200 && $req['client_reference_id'] === $row->reference
        && str_contains($req['success_url'], '/order/'.$order->token.'/pay/return') && $req->hasHeader('Authorization', 'Bearer sk_test_123'));
    expect(opFresh($r, $order)->isPaid())->toBeFalse(); // not paid until the gateway says so
});

it('splits the bill equally, takes any amount, and validates the input', function () {
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.test/c'])]);
    [$r, , $d] = opShop();
    opStripe($r);
    $order = opOrder($r, $d, [], 3); // $60.00
    $url = '/r/'.$r->slug.'/order/'.$order->token.'/pay';
    $this->postJson($url, ['gateway' => 'stripe', 'mode' => 'split', 'people' => 4, 'tip_percent' => 0])->assertOk();
    $this->postJson($url, ['gateway' => 'stripe', 'mode' => 'custom', 'amount' => '12.34', 'tip' => '1.5'])->assertOk();
    $this->postJson($url, ['gateway' => 'stripe', 'mode' => 'custom', 'amount' => '9999'])->assertOk(); // capped at what is owed
    $rows = app(TenantContext::class)->runAs($r, fn () => OrderPayment::orderBy('id')->get());
    expect($rows->pluck('amount_cents')->all())->toBe([1500, 1234, 6000])->and($rows[1]->tip_cents)->toBe(150);

    $this->postJson($url, ['gateway' => 'stripe', 'mode' => 'custom', 'amount' => '0'])->assertStatus(422);
    $this->postJson($url, ['gateway' => 'stripe', 'mode' => 'split', 'people' => 1])->assertStatus(422);
    $this->postJson($url, ['gateway' => 'stripe', 'mode' => 'full', 'tip_percent' => 99])->assertStatus(422);
    $this->postJson($url, ['gateway' => 'paypal', 'mode' => 'full'])->assertStatus(422)->assertJsonPath('error', 'payment_unavailable');
});

it('pays a whole table in one checkout, oldest order first', function () {
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_tab', 'url' => 'https://checkout.stripe.test/tab'])]);
    [$r, , $d] = opShop();
    opStripe($r);
    $a = opOrder($r, $d, ['type' => 'dine_in', 'table_id' => $d['t1']->id]);
    $b = opOrder($r, $d, ['type' => 'dine_in', 'table_id' => $d['t1']->id], 2);
    $state = $this->getJson('/r/'.$r->slug.'/order/'.$b->token.'/status')->json('pay');
    expect($state['tab']['count'])->toBe(2)->and($state['tab']['cents'])->toBe(6000);

    $this->postJson('/r/'.$r->slug.'/order/'.$b->token.'/pay', ['gateway' => 'stripe', 'mode' => 'tab', 'tip_percent' => 5])->assertOk();
    $rows = app(TenantContext::class)->runAs($r, fn () => OrderPayment::orderBy('id')->get());
    expect($rows)->toHaveCount(2)->and($rows->pluck('reference')->unique())->toHaveCount(1)->and($rows->sum('amount_cents'))->toBe(6000)->and($rows[0]->tip_cents)->toBe(300);

    // One verified notification settles both orders.
    opWebhook($this, $r, $rows[0]->reference, 6300)->assertOk();
    expect(opFresh($r, $a)->isPaid())->toBeTrue()->and(opFresh($r, $b)->isPaid())->toBeTrue()->and(opFresh($r, $a)->tip_cents)->toBe(300);
});

it('settles a payment from a signed webhook, once, for the right amount only', function () {
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.test/c'])]);
    Mail::fake();
    [$r, , $d] = opShop();
    opStripe($r);
    $order = opOrder($r, $d);
    $this->postJson('/r/'.$r->slug.'/order/'.$order->token.'/pay', ['gateway' => 'stripe', 'mode' => 'full', 'tip_percent' => 10])->assertOk();
    $ref = app(TenantContext::class)->runAs($r, fn () => OrderPayment::first()->reference);

    opWebhook($this, $r, $ref, 2200, 'wrong_secret')->assertStatus(400);
    expect(opFresh($r, $order)->isPaid())->toBeFalse();
    opWebhook($this, $r, $ref, 100)->assertOk(); // a smaller amount than asked is not accepted
    expect(opFresh($r, $order)->isPaid())->toBeFalse();

    opWebhook($this, $r, $ref, 2200, 'whsec_test', 'pi_42')->assertOk();
    opWebhook($this, $r, $ref, 2200, 'whsec_test', 'pi_42')->assertOk(); // delivered twice
    $o = opFresh($r, $order);
    expect($o->paid_cents)->toBe(2000)->and($o->tip_cents)->toBe(200)->and($o->isPaid())->toBeTrue();
    expect(app(TenantContext::class)->runAs($r, fn () => OrderPayment::first()))->transaction_id->toBe('pi_42')->status->toBe('paid');

    // The paid receipt is e-mailed once, with a PDF.
    Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'order_receipt' && $m->hasTo('g@example.com'));
    expect(Mail::sent(TemplatedMail::class)->filter(fn ($m) => $m->templateKey === 'order_receipt'))->toHaveCount(1);
});

it('confirms the return from the hosted page by asking the gateway, never by trusting the URL', function () {
    Http::fake([
        'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_ret', 'url' => 'https://checkout.stripe.test/c']),
    ]);
    [$r, , $d] = opShop();
    opStripe($r);
    $order = opOrder($r, $d);
    $this->postJson('/r/'.$r->slug.'/order/'.$order->token.'/pay', ['gateway' => 'stripe', 'mode' => 'full'])->assertOk();
    $ref = app(TenantContext::class)->runAs($r, fn () => OrderPayment::first()->reference);
    $back = '/r/'.$r->slug.'/order/'.$order->token.'/pay/return?ref='.$ref.'&session_id=cs_ret';

    // Gateway says "unpaid" first: a guest typing the return URL gets nothing. Then it says "paid".
    Http::fake(['api.stripe.com/v1/checkout/sessions/cs_ret' => Http::sequence()
        ->push(['id' => 'cs_ret', 'client_reference_id' => $ref, 'payment_status' => 'unpaid'])
        ->push(['id' => 'cs_ret', 'client_reference_id' => $ref, 'payment_status' => 'paid', 'payment_intent' => 'pi_ret', 'amount_total' => 2000, 'currency' => 'usd'])]);
    $this->get($back)->assertRedirect()->assertSessionHas('pay_result', 'pending');
    expect(opFresh($r, $order)->isPaid())->toBeFalse();

    $this->get($back)->assertRedirect()->assertSessionHas('pay_result', 'paid');
    expect(opFresh($r, $order)->isPaid())->toBeTrue();

    $this->get('/r/'.$r->slug.'/order/'.$order->token.'/pay/return?ref=R999P999')->assertRedirect(); // unknown reference: nothing happens
});

it('refunds an online payment through Stripe', function () {
    Http::fake(['api.stripe.com/v1/refunds' => Http::sequence()->push(['id' => 're_1'])->push(['error' => ['message' => 'nope']], 400)]);
    [$r, $owner, $d] = opShop();
    opStripe($r);
    $order = opOrder($r, $d);
    $payment = app(TenantContext::class)->runAs($r, fn () => app(PaymentLedger::class)->record($order, 'online', null, 0, null, ['gateway' => 'stripe', 'transaction_id' => 'pi_77', 'reference' => 'R1P1']));
    $this->actingAs($owner)->post(route('orders.payments.refund', $payment->id), ['amount' => '8.5'])->assertRedirect()->assertSessionHas('status', __('orders.refunded_via_gateway'));
    Http::assertSent(fn ($req) => str_contains($req->url(), '/v1/refunds') && $req['payment_intent'] === 'pi_77' && $req['amount'] === 850);
    expect(opFresh($r, $order)->refunded_cents)->toBe(850);

    $this->actingAs($owner)->post(route('orders.payments.refund', $payment->id), ['amount' => '1'])->assertSessionHasErrors('order');
    expect(opFresh($r, $order)->refunded_cents)->toBe(850); // nothing recorded when the gateway said no
});

it('keeps the platform commission on online payments and adds it to the next invoice', function () {
    [$r, , $d] = opShop();
    app(SettingsService::class)->set('guest_payments.commission_percent', '5');
    $order = opOrder($r, $d);
    app(TenantContext::class)->runAs($r, fn () => app(PaymentLedger::class)->record($order, 'online', null, 300, null, ['gateway' => 'stripe']));
    $row = app(TenantContext::class)->runAs($r, fn () => OrderPayment::first());
    expect($row->commission_cents)->toBe(100); // 5% of $20.00, tips excluded

    $cash = opOrder($r, $d);
    app(TenantContext::class)->runAs($r, fn () => app(PaymentLedger::class)->record($cash, 'cash'));
    expect(app(TenantContext::class)->runAs($r, fn () => OrderPayment::where('method', 'cash')->first()->commission_cents))->toBe(0);

    $plan = Plan::first();
    $invoice = app(InvoiceService::class)->create($r, $plan);
    expect($invoice->items)->toHaveCount(2)->and((float) $invoice->items[1]['amount'])->toBe(1.0)->and((float) $invoice->total)->toBe(11.0);
    expect(app(TenantContext::class)->runAs($r, fn () => OrderPayment::find($row->id)->commission_billed_at))->not->toBeNull();
    // Billed once only.
    expect((float) app(InvoiceService::class)->create($r, $plan)->total)->toBe(10.0);
});

it('leaves commission in another currency unbilled', function () {
    [$r, , $d] = opShop();
    app(SettingsService::class)->set('guest_payments.commission_percent', '10');
    $order = opOrder($r, $d);
    app(TenantContext::class)->runAs($r, fn () => app(PaymentLedger::class)->record($order, 'online', null, 0, null, ['gateway' => 'stripe']));
    Plan::first()->update(['currency_code' => 'EUR']);
    expect((float) app(InvoiceService::class)->create($r, Plan::first())->total)->toBe(10.0);
    expect(app(TenantContext::class)->runAs($r, fn () => OrderPayment::first()->commission_billed_at))->toBeNull();
});

it('lets the platform admin set commission and allowed gateways', function () {
    $admin = User::factory()->create(['restaurant_id' => null]);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $admin->assignRole(Permissions::SUPER_ADMIN);
    $this->actingAs($admin)->get(route('admin.settings.guest-payments'))->assertOk()->assertSee(__('admin.guest_payments.commission'));
    $this->actingAs($admin)->put(route('admin.settings.guest-payments.update'), ['enabled' => 1, 'commission_percent' => '2.5', 'allowed' => ['stripe', 'mollie']])->assertRedirect();
    $g = app(RestaurantGateways::class);
    expect($g->commissionPercent())->toBe(2.5)->and(array_keys($g->allowed()))->toBe(['stripe', 'mollie']);
    $this->actingAs($admin)->put(route('admin.settings.guest-payments.update'), ['commission_percent' => '80'])->assertSessionHasErrors('commission_percent');

    [, $owner] = opShop();
    $this->actingAs($owner)->get(route('admin.settings.guest-payments'))->assertForbidden();
    // With the platform switch off nobody can take online payments.
    $this->actingAs($admin)->put(route('admin.settings.guest-payments.update'), ['commission_percent' => '0', 'allowed' => ['stripe']])->assertRedirect();
    [$r] = opShop();
    opStripe($r);
    expect($g->availableFor($r))->toBe([]);
});

it('shows a printable digital receipt and a PDF with the payments on it', function () {
    [$r, , $d] = opShop();
    $order = opOrder($r, $d);
    app(TenantContext::class)->runAs($r, fn () => app(PaymentLedger::class)->record($order, 'cash', null, 250));
    $page = $this->get('/r/'.$r->slug.'/order/'.$order->token.'/receipt')->assertOk()->assertSee('Pay Bistro')->assertSee('$20.00')->assertSee('$2.50')->assertSee(__('orders.paid'))->assertSee('<svg', false)->getContent();
    expect($page)->toContain('receipt.pdf');
    $pdf = $this->get('/r/'.$r->slug.'/order/'.$order->token.'/receipt.pdf')->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('pdf')->and(substr($pdf->getContent(), 0, 4))->toBe('%PDF');
    $this->get('/r/'.$r->slug.'/order/'.str_repeat('q', 24).'/receipt')->assertNotFound();
    [$r2] = opShop();
    $this->get('/r/'.$r2->slug.'/order/'.$order->token.'/receipt')->assertNotFound(); // another restaurant's menu cannot show it
});

it('invites happy guests to review publicly, with the owner’s own link', function () {
    [$r, $owner, $d] = opShop();
    $this->actingAs($owner)->put(route('marketing.settings.update'), ['loyalty_every' => 5, 'loyalty_reward_type' => 'percent', 'loyalty_reward_value' => 10, 'loyalty_valid_days' => 60, 'campaign_daily_cap' => 50, 'reviews_enabled' => 1, 'review_url' => 'https://g.page/r/abc/review', 'review_min' => 4])->assertRedirect();
    $this->actingAs($owner)->put(route('marketing.settings.update'), ['loyalty_every' => 5, 'loyalty_reward_type' => 'percent', 'loyalty_reward_value' => 10, 'loyalty_valid_days' => 60, 'campaign_daily_cap' => 50, 'review_url' => 'javascript:alert(1)'])->assertSessionHasErrors('review_url');

    $order = opOrder($r, $d);
    app(TenantContext::class)->runAs($r, fn () => Order::find($order->id)->forceFill(['status' => 'completed', 'completed_at' => now()])->save());
    $this->post('/r/'.$r->slug.'/order/'.$order->token.'/review', ['rating' => 5]);
    expect($this->getJson('/r/'.$r->slug.'/order/'.$order->token.'/status')->json('review.redirect'))->toBe('https://g.page/r/abc/review');

    $low = opOrder($r, $d);
    app(TenantContext::class)->runAs($r, fn () => Order::find($low->id)->forceFill(['status' => 'completed', 'completed_at' => now()])->save());
    $this->post('/r/'.$r->slug.'/order/'.$low->token.'/review', ['rating' => 2]);
    expect($this->getJson('/r/'.$r->slug.'/order/'.$low->token.'/status')->json('review.redirect'))->toBeNull();
});

it('renders the pay box on the status page', function () {
    [$r, , $d] = opShop();
    opStripe($r);
    $order = opOrder($r, $d);
    $this->get('/r/'.$r->slug.'/order/'.$order->token.'?pay=1')->assertOk()->assertSee(__('orders.pay_now'))->assertSee(__('orders.pay_split'))->assertSee(__('orders.tip'));
    $this->get('/r/'.$r->slug)->assertOk()->assertSee(__('orders.pay_online'));
});
