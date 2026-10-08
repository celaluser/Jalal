<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Models\PromoCode;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\DeliveryZone;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderPayment;
use App\Modules\Orders\Models\PrintJob;
use App\Modules\Orders\Models\Shift;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\OrderSettings;
use App\Modules\Orders\Services\PaymentLedger;
use App\Modules\Orders\Services\TicketPrinter;
use App\Modules\Orders\Support\EscPos;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    Cache::flush();
});

function opsShop(array $settings = []): array
{
    $r = Restaurant::create(['name' => 'Ops Bistro', 'slug' => 'ops'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now(), 'order_settings' => $settings]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $owner = opsUser($r, Permissions::OWNER);
    $r->forceFill(['owner_id' => $owner->id])->save();
    $d = app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Food'], 'sort' => 1]);

        return [
            'pizza' => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Pizza'], 'price' => 20, 'sort' => 1, 'station' => 'Kitchen']),
            'cola' => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Cola'], 'price' => 3, 'sort' => 2, 'station' => 'Bar', 'stock_qty' => 5, 'low_stock_at' => 2]),
            't1' => DiningTable::create(['name' => 'T1']),
        ];
    });

    return [$r, $owner, $d];
}

function opsUser(Restaurant $r, string $role, array $attrs = []): User
{
    $u = User::factory()->create(['restaurant_id' => $r->id] + $attrs);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $u->assignRole($role);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return $u;
}

function opsOrder(Restaurant $r, array $d, array $extra = [], ?array $lines = null, string $source = 'qr'): Order
{
    return app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, $extra + ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => $lines ?? [['product_id' => $d['pizza']->id, 'qty' => 1]]], $source));
}

function opsFresh(Restaurant $r, Order $o): Order
{
    return app(TenantContext::class)->runAs($r, fn () => Order::find($o->id));
}

function opsSet(Restaurant $r, Order $o, array $attrs): void
{
    app(TenantContext::class)->runAs($r, fn () => Order::find($o->id)->forceFill($attrs)->save());
}

// ---- order history ------------------------------------------------------------------------

it('filters the order history and exports it safely', function () {
    [$r, $owner, $d] = opsShop(['delivery' => true]);
    $a = opsOrder($r, $d, ['customer_name' => 'Alice']);
    $b = opsOrder($r, $d, ['customer_name' => '=HYPERLINK("x")', 'type' => 'delivery', 'delivery_address' => '1 Long Street']);
    opsSet($r, $b, ['status' => 'completed', 'paid_at' => now()]);
    opsSet($r, $a, ['created_at' => now()->subDays(10)]);

    $page = fn (array $q = []) => $this->actingAs($owner)->get(route('orders.history', $q))->assertOk()->getContent();
    expect($page())->toContain('Alice')->toContain('HYPERLINK');
    expect($page(['q' => 'alice']))->toContain('Alice')->not->toContain('HYPERLINK');
    expect($page(['status' => 'completed']))->not->toContain('Alice');
    expect($page(['type' => 'delivery']))->not->toContain('Alice');
    expect($page(['paid' => 'no']))->toContain('Alice')->not->toContain('HYPERLINK');
    expect($page(['from' => now()->subDays(2)->toDateString()]))->not->toContain('Alice')->toContain('HYPERLINK');
    expect($page(['q' => (string) $a->number]))->toContain('Alice');
    $this->actingAs($owner)->get(route('orders.history', ['status' => 'bogus']))->assertSessionHasErrors('status');

    $csv = $this->actingAs($owner)->get(route('orders.history.export'))->assertOk()->getContent();
    expect($csv)->toContain('"Alice"')->toContain("\"'=HYPERLINK")->not->toContain('"=HYPERLINK');
});

it('keeps order history to people who manage orders and to their own restaurant', function () {
    [$r, , $d] = opsShop();
    opsOrder($r, $d);
    $this->actingAs(opsUser($r, Permissions::KITCHEN))->get(route('orders.history'))->assertForbidden();
    $this->actingAs(opsUser($r, Permissions::WAITER))->get(route('orders.history'))->assertForbidden();
    [, $other] = opsShop();
    $this->actingAs($other)->get(route('orders.history'))->assertOk()->assertDontSee('555 111');
});

// ---- discount and closing a table ------------------------------------------------------------

it('gives a staff discount on top of a promo code and works the totals out again', function () {
    [$r, $owner, $d] = opsShop(['service_rate' => '10', 'tax_rate' => '10', 'prices_include_tax' => false]);
    app(TenantContext::class)->runAs($r, fn () => PromoCode::create(['code' => 'TEN', 'type' => 'percent', 'value' => 10, 'is_active' => true]));
    $order = opsOrder($r, $d, ['type' => 'dine_in', 'table_id' => $d['t1']->id, 'promo_code' => 'TEN']); // 2000 - 200 promo = 1800 + 180 service = 1980 + 198 tax
    expect($order->total_cents)->toBe(2178);

    $this->actingAs($owner)->post(route('orders.discount', $order->id), ['type' => 'fixed', 'value' => '3.00', 'reason' => 'Late food'])->assertRedirect();
    $o = opsFresh($r, $order); // (2000 - 200 - 300) = 1500 + 150 + 165
    expect($o->manual_discount_cents)->toBe(300)->and($o->total_cents)->toBe(1815);

    $this->actingAs($owner)->post(route('orders.discount', $order->id), ['type' => 'percent', 'value' => '100'])->assertRedirect();
    expect(opsFresh($r, $order)->manual_discount_cents)->toBe(1800)->and(opsFresh($r, $order)->total_cents)->toBe(0); // capped at what is left
    $this->actingAs($owner)->post(route('orders.discount', $order->id), ['type' => 'percent', 'value' => '0'])->assertRedirect();
    expect(opsFresh($r, $order)->total_cents)->toBe(2178);
    $this->actingAs($owner)->post(route('orders.discount', $order->id), ['type' => 'percent', 'value' => '150'])->assertSessionHasErrors('order');
    $this->actingAs($owner)->get(route('orders.show', $order->id))->assertOk()->assertSee(__('orders.discount'));
});

it('refuses a discount on a paid or cancelled order and from staff who may not give one', function () {
    [$r, $owner, $d] = opsShop();
    $paid = opsOrder($r, $d);
    app(TenantContext::class)->runAs($r, fn () => app(PaymentLedger::class)->record($paid, 'cash'));
    $this->actingAs($owner)->post(route('orders.discount', $paid->id), ['type' => 'fixed', 'value' => '1'])->assertSessionHasErrors('order');

    $open = opsOrder($r, $d);
    $this->actingAs(opsUser($r, Permissions::WAITER))->post(route('orders.discount', $open->id), ['type' => 'fixed', 'value' => '1'])->assertForbidden();
    opsSet($r, $open, ['status' => 'cancelled']);
    $this->actingAs($owner)->post(route('orders.discount', $open->id), ['type' => 'fixed', 'value' => '1'])->assertSessionHasErrors('order');
});

it('settles a part-paid bill when the discount covers the rest', function () {
    [$r, $owner, $d] = opsShop();
    $order = opsOrder($r, $d); // $20
    app(TenantContext::class)->runAs($r, fn () => app(PaymentLedger::class)->record($order, 'cash', 1500));
    $this->actingAs($owner)->post(route('orders.discount', $order->id), ['type' => 'fixed', 'value' => '5'])->assertRedirect();
    expect(opsFresh($r, $order)->isPaid())->toBeTrue();
});

it('closes a table: every open order paid at once, tip once', function () {
    [$r, $owner, $d] = opsShop();
    $a = opsOrder($r, $d, ['type' => 'dine_in', 'table_id' => $d['t1']->id]);
    $b = opsOrder($r, $d, ['type' => 'dine_in', 'table_id' => $d['t1']->id]);
    $c = opsOrder($r, $d, ['type' => 'dine_in', 'table_id' => $d['t1']->id]);
    opsSet($r, $c, ['status' => 'cancelled']);

    $this->actingAs($owner)->post(route('orders.tables.close', $d['t1']->id), ['method' => 'card', 'tip' => '4'])->assertRedirect();
    expect(opsFresh($r, $a)->isPaid())->toBeTrue()->and(opsFresh($r, $b)->isPaid())->toBeTrue()->and(opsFresh($r, $c)->isPaid())->toBeFalse()
        ->and(opsFresh($r, $a)->tip_cents)->toBe(400)->and(opsFresh($r, $b)->tip_cents)->toBe(0);
    $this->actingAs($owner)->postJson(route('orders.tables.close', $d['t1']->id), ['method' => 'cash'])->assertOk()->assertJsonPath('settled', 0);
    $this->actingAs(opsUser($r, Permissions::KITCHEN))->post(route('orders.tables.close', $d['t1']->id), ['method' => 'cash'])->assertForbidden();
});

// ---- cash shifts ---------------------------------------------------------------------------

it('counts a shift: opening cash, cash taken, cash refunded, and the difference', function () {
    [$r, , $d] = opsShop();
    $cashier = opsUser($r, Permissions::CASHIER);
    $order = opsOrder($r, $d); // $20
    $payment = app(TenantContext::class)->runAs($r, fn () => null);

    $this->actingAs($cashier)->post(route('shifts.open'), ['opening' => '100'])->assertRedirect();
    $this->actingAs($cashier)->post(route('shifts.open'), ['opening' => '50'])->assertSessionHasErrors('shift');
    $shift = app(TenantContext::class)->runAs($r, fn () => Shift::first());
    expect($shift->opening_cents)->toBe(10000);

    $this->actingAs($cashier)->post(route('orders.payments.add', $order->id), ['method' => 'cash', 'amount' => '15', 'tip' => '2'])->assertRedirect();
    $this->actingAs($cashier)->post(route('orders.pay', $order->id), ['method' => 'card'])->assertRedirect(); // $5 left by card
    $cashPayment = app(TenantContext::class)->runAs($r, fn () => OrderPayment::where('method', 'cash')->first());
    $this->actingAs($cashier)->post(route('orders.payments.refund', $cashPayment->id), ['amount' => '3'])->assertRedirect();

    $page = $this->actingAs($cashier)->get(route('shifts.index'))->assertOk();
    $page->assertSee('$114.00'); // 100 + 15 + 2 tip - 3 refund
    $this->actingAs($cashier)->post(route('shifts.close', $shift->id), ['counted' => '113.50', 'note' => 'a coin missing'])->assertRedirect();
    $closed = app(TenantContext::class)->runAs($r, fn () => Shift::find($shift->id));
    expect($closed->expected_cents)->toBe(11400)->and($closed->closing_cents)->toBe(11350)->and($closed->difference())->toBe(-50)->and($closed->summary['methods']['card']['amount'])->toBe(500)->and($closed->isOpen())->toBeFalse();
    $this->actingAs($cashier)->post(route('shifts.close', $shift->id), ['counted' => '1'])->assertNotFound();
    $this->actingAs($cashier)->get(route('shifts.index'))->assertSee('−$0.50');
});

it('shows cashiers their own shifts and managers all of them', function () {
    [$r, $owner] = opsShop();
    $a = opsUser($r, Permissions::CASHIER, ['name' => 'Cashier Anna']);
    $b = opsUser($r, Permissions::CASHIER, ['name' => 'Cashier Bob']);
    foreach ([$a, $b] as $u) {
        $this->actingAs($u)->post(route('shifts.open'), ['opening' => '10']);
        $s = app(TenantContext::class)->runAs($r, fn () => Shift::where('user_id', $u->id)->first());
        $this->actingAs($u)->post(route('shifts.close', $s->id), ['counted' => '10']);
    }
    $this->actingAs($a)->get(route('shifts.index'))->assertSee('Cashier Anna')->assertDontSee('Cashier Bob');
    $this->actingAs($owner)->get(route('shifts.index'))->assertSee('Cashier Anna')->assertSee('Cashier Bob');
    $this->actingAs($a)->post(route('shifts.close', app(TenantContext::class)->runAs($r, fn () => Shift::where('user_id', $b->id)->first())->id), ['counted' => '1'])->assertStatus(404);
    $this->actingAs(opsUser($r, Permissions::KITCHEN))->get(route('shifts.index'))->assertForbidden();
});

// ---- delivery zones and couriers ---------------------------------------------------------------

it('charges the fee and minimum of the chosen delivery zone', function () {
    [$r, $owner, $d] = opsShop(['delivery' => true, 'delivery_fee' => '1']);
    expect(opsOrder($r, $d, ['type' => 'delivery', 'delivery_address' => '1 Long Street'])->delivery_cents)->toBe(100); // no zones: the single fee

    $this->actingAs($owner)->post(route('delivery.zones.store'), ['name' => 'Old town', 'fee' => '2.50', 'min_order' => '15'])->assertRedirect();
    $this->actingAs($owner)->post(route('delivery.zones.store'), ['name' => 'Suburbs', 'fee' => '6', 'min_order' => '40'])->assertRedirect();
    [$near, $far] = app(TenantContext::class)->runAs($r, fn () => DeliveryZone::orderBy('id')->get()->all());

    try {
        opsOrder($r, $d, ['type' => 'delivery', 'delivery_address' => '1 Long Street']);
        throw new RuntimeException('should need a zone');
    } catch (OrderException $e) {
        expect($e->reason)->toBe('zone_required');
    }
    $ok = opsOrder($r, $d, ['type' => 'delivery', 'delivery_address' => '1 Long Street', 'delivery_zone' => $near->id]);
    expect($ok->delivery_cents)->toBe(250)->and($ok->delivery_zone)->toBe('Old town')->and($ok->total_cents)->toBe(2250);
    try {
        opsOrder($r, $d, ['type' => 'delivery', 'delivery_address' => '1 Long Street', 'delivery_zone' => $far->id]); // $20 is under the $40 minimum
        throw new RuntimeException('should be below minimum');
    } catch (OrderException $e) {
        expect($e->reason)->toBe('below_minimum');
    }
    app(TenantContext::class)->runAs($r, fn () => $near->update(['is_active' => false]));
    try {
        opsOrder($r, $d, ['type' => 'delivery', 'delivery_address' => '1 Long Street', 'delivery_zone' => $near->id]);
        throw new RuntimeException('inactive zone');
    } catch (OrderException $e) {
        expect($e->reason)->toBe('zone_required');
    }

    // The menu offers the zones and the quote follows the choice.
    $html = $this->get('/r/'.$r->slug)->assertOk()->assertSee(__('orders.zone_label'))->getContent();
    expect($html)->toContain('Suburbs')->not->toContain('Old town');
    $q = $this->postJson('/r/'.$r->slug.'/cart/quote', ['lines' => [['product_id' => $d['pizza']->id, 'qty' => 3]], 'type' => 'delivery', 'delivery_zone' => $far->id])->assertOk();
    expect($q->json('totals.raw.delivery'))->toBe(600);
    $this->actingAs(opsUser($r, Permissions::WAITER))->get(route('delivery.zones'))->assertForbidden();
});

it('hands a delivery to a courier who takes it out and collects the money', function () {
    [$r, $owner, $d] = opsShop(['delivery' => true]);
    $courier = opsUser($r, Permissions::DELIVERY, ['name' => 'Carl Courier']);
    $other = opsUser($r, Permissions::DELIVERY);
    $order = opsOrder($r, $d, ['type' => 'delivery', 'delivery_address' => '1 Long Street']);
    foreach (['accepted', 'preparing', 'ready'] as $to) {
        $this->actingAs($owner)->postJson(route('orders.status', $order->id), ['status' => $to])->assertOk();
    }

    $this->actingAs($owner)->post(route('orders.courier', $order->id), ['courier_id' => $courier->id])->assertRedirect();
    $this->actingAs($owner)->post(route('orders.courier', $order->id), ['courier_id' => opsUser($r, Permissions::KITCHEN)->id])->assertSessionHasErrors('order');
    $this->actingAs($owner)->get(route('orders.show', $order->id))->assertSee('Carl Courier');

    $this->actingAs($courier)->get(route('courier.index'))->assertOk()->assertSee('1 Long Street')->assertSee(__('orders.courier_cash'));
    $this->actingAs($other)->get(route('courier.index'))->assertDontSee('1 Long Street');
    $this->actingAs($other)->post(route('courier.leave', $order->id))->assertNotFound(); // not theirs
    $this->actingAs($courier)->post(route('courier.leave', $order->id))->assertRedirect();
    expect(opsFresh($r, $order)->dispatched_at)->not->toBeNull();
    $this->actingAs($courier)->post(route('courier.delivered', $order->id), ['collected' => 'cash'])->assertRedirect();
    $o = opsFresh($r, $order);
    expect($o->status)->toBe('completed')->and($o->isPaid())->toBeTrue()->and($o->payment_method)->toBe('cash');
    $this->actingAs(opsUser($r, Permissions::KITCHEN))->get(route('courier.index'))->assertForbidden();
});

it('lets a courier pick up a ready delivery nobody has', function () {
    [$r, $owner, $d] = opsShop(['delivery' => true]);
    $courier = opsUser($r, Permissions::DELIVERY);
    $order = opsOrder($r, $d, ['type' => 'delivery', 'delivery_address' => '2 Short Road']);
    foreach (['accepted', 'preparing', 'ready'] as $to) {
        $this->actingAs($owner)->postJson(route('orders.status', $order->id), ['status' => $to]);
    }
    $this->actingAs($courier)->get(route('courier.index'))->assertSee('2 Short Road');
    $this->actingAs($courier)->post(route('courier.claim', $order->id))->assertRedirect();
    expect(opsFresh($r, $order)->courier_id)->toBe($courier->id);
    $this->actingAs(opsUser($r, Permissions::DELIVERY))->post(route('courier.claim', $order->id))->assertNotFound(); // already taken
});

// ---- alerts ---------------------------------------------------------------------------------

it('e-mails the owner once when a dish runs low, and again after a restock', function () {
    Mail::fake();
    [$r, , $d] = opsShop();
    $cola = fn (int $qty) => opsOrder($r, $d, [], [['product_id' => $d['cola']->id, 'qty' => $qty]]);
    $lowMails = fn () => Mail::sent(TemplatedMail::class)->filter(fn ($m) => $m->templateKey === 'low_stock')->count();

    $cola(2); // 5 -> 3: above the warning level of 2
    expect($lowMails())->toBe(0);
    $cola(1); // 3 -> 2
    expect($lowMails())->toBe(1);
    $cola(1); // 2 -> 1: already told
    expect($lowMails())->toBe(1);

    app(TenantContext::class)->runAs($r, fn () => Product::find($d['cola']->id)->update(['stock_qty' => 10]));
    $cola(8); // 10 -> 2
    expect($lowMails())->toBe(2);
    Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'low_stock' && $m->hasTo(User::first()->email) && str_contains(json_encode($m->vars), 'Cola'));
});

it('e-mails the owner about an order nobody accepted, once, only when asked', function () {
    Mail::fake();
    [$r, , $d] = opsShop(['escalate_minutes' => 5]);
    $late = opsOrder($r, $d);
    $fresh = opsOrder($r, $d);
    opsSet($r, $late, ['created_at' => now()->subMinutes(9)]);
    $this->artisan('orders:escalate')->assertSuccessful();
    $this->artisan('orders:escalate')->assertSuccessful();
    $sent = Mail::sent(TemplatedMail::class)->filter(fn ($m) => $m->templateKey === 'order_unaccepted');
    expect($sent)->toHaveCount(1)->and(json_encode($sent->first()->vars))->toContain('#'.$late->number);
    expect(opsFresh($r, $fresh)->escalated_at)->toBeNull();

    // Accepted orders and restaurants that did not ask are left alone.
    [$r2, , $d2] = opsShop();
    $o = opsOrder($r2, $d2);
    opsSet($r2, $o, ['created_at' => now()->subMinutes(30)]);
    $this->artisan('orders:escalate')->assertSuccessful();
    expect(Mail::sent(TemplatedMail::class)->filter(fn ($m) => $m->templateKey === 'order_unaccepted'))->toHaveCount(1);
});

it('gives the board the unaccepted warning level', function () {
    [$r, $owner] = opsShop(['alert_unaccepted' => 7]);
    $this->actingAs($owner)->get(route('orders.board'))->assertOk()->assertSee('alertAfter\\u0022:7', false);
});

// ---- kitchen display ---------------------------------------------------------------------------

it('shows the kitchen display and lets each station tick off its part', function () {
    [$r, $owner, $d] = opsShop(['stations' => 'Kitchen, Bar']);
    $kitchen = opsUser($r, Permissions::KITCHEN);
    $order = opsOrder($r, $d, [], [['product_id' => $d['pizza']->id, 'qty' => 1], ['product_id' => $d['cola']->id, 'qty' => 2]]);
    $this->actingAs($kitchen)->get(route('orders.kds'))->assertOk()->assertSee(__('orders.kds_title'))->assertSee('Bar\\u0022', false);
    $feed = $this->actingAs($kitchen)->getJson(route('orders.feed'))->json('orders.0.items');
    expect(collect($feed)->pluck('station')->all())->toBe(['Kitchen', 'Bar']);

    $this->actingAs($kitchen)->postJson(route('orders.status', $order->id), ['status' => 'preparing'])->assertOk();
    $this->actingAs($kitchen)->postJson(route('orders.station-done', $order->id), ['station' => 'Bar'])->assertOk();
    expect(opsFresh($r, $order)->status)->toBe('preparing'); // the kitchen is not done yet
    $this->actingAs($kitchen)->postJson(route('orders.station-done', $order->id), ['station' => 'Kitchen'])->assertOk();
    expect(opsFresh($r, $order)->status)->toBe('ready');

    // Without stations one tap finishes everything, straight from "new".
    $solo = opsOrder($r, $d);
    $this->actingAs($kitchen)->postJson(route('orders.station-done', $solo->id), [])->assertOk();
    expect(opsFresh($r, $solo)->status)->toBe('ready');
    $this->actingAs(opsUser($r, Permissions::DELIVERY))->postJson(route('orders.station-done', $solo->id), [])->assertForbidden();
});

// ---- thermal printing -------------------------------------------------------------------------------

it('builds ESC/POS bytes: widths, code pages, QR and cut', function () {
    $p = (new EscPos(20, 'cp857'))->columns('Pizza', '$20.00')->text('Çay şeker')->qr('https://x.test/r')->cut()->bytes();
    expect($p)->toStartWith("\x1B@")->toContain("Pizza         \$20.00\n")->toContain("\x1D(k")->toContain('https://x.test/r')->toContain("\x1DV");
    expect($p)->toContain("\x80ay \x9f\x65ker"); // Ç, ş, ğ in CP857, not UTF-8

    $long = (new EscPos(16, 'ascii'))->columns('A very long dish name', '$9.99')->bytes();
    expect($long)->toContain("A very lon \$9.99\n")->not->toContain('very long');
    expect((new EscPos(16, 'ascii'))->text('Ünïcode €')->bytes())->not->toContain("\xC3");
});

it('queues kitchen tickets and receipts by itself when asked', function () {
    [$r, , $d] = opsShop(['print_auto_kitchen' => true, 'print_auto_receipt' => true]);
    $order = opsOrder($r, $d, ['note' => 'no onions']);
    $jobs = fn () => app(TenantContext::class)->runAs($r, fn () => PrintJob::orderBy('id')->get());
    expect($jobs())->toHaveCount(1)->and($jobs()[0]->kind)->toBe('kitchen');
    $ticket = base64_decode($jobs()[0]->payload);
    expect($ticket)->toContain('#'.$order->number)->toContain('Pizza')->toContain('no onions')->not->toContain('20.00'); // the kitchen does not need prices

    app(TenantContext::class)->runAs($r, fn () => app(PaymentLedger::class)->record($order, 'cash', null, 100));
    expect($jobs())->toHaveCount(2);
    $receipt = base64_decode($jobs()[1]->payload);
    expect($receipt)->toContain('$20.00')->toContain('Tip')->toContain("\x1D(k")->toContain('/receipt');

    [$r2, , $d2] = opsShop();
    opsOrder($r2, $d2);
    expect(app(TenantContext::class)->runAs($r2, fn () => PrintJob::count()))->toBe(0);
});

it('hands tickets to the bridge, offers a lost one again, and ignores a wrong token', function () {
    [$r, $owner, $d] = opsShop();
    $order = opsOrder($r, $d);
    $token = app(TicketPrinter::class)->token($r);
    app(TenantContext::class)->runAs($r, fn () => app(TicketPrinter::class)->enqueue(Order::find($order->id), 'kitchen'));

    $this->getJson("/print/{$token}/next")->assertOk()->assertJsonPath('kind', 'kitchen')->assertJsonStructure(['id', 'payload']);
    $this->getJson("/print/{$token}/next")->assertNoContent(); // handed out, waiting to be confirmed
    $job = app(TenantContext::class)->runAs($r, fn () => PrintJob::first());

    $this->travel(60)->seconds();
    $this->getJson("/print/{$token}/next")->assertOk()->assertJsonPath('id', $job->id); // not confirmed: offered again
    $this->postJson("/print/{$token}/{$job->id}/done")->assertOk();
    expect(app(TenantContext::class)->runAs($r, fn () => PrintJob::first()->status))->toBe('done');
    $this->travel(120)->seconds();
    $this->getJson("/print/{$token}/next")->assertNoContent();

    $this->getJson('/print/'.$r->id.'-'.str_repeat('a', 32).'/next')->assertNotFound();
    $this->getJson('/print/nonsense/next')->assertNotFound();
    // Another restaurant's token never shows this restaurant's tickets.
    [$r2] = opsShop();
    $this->getJson('/print/'.app(TicketPrinter::class)->token($r2).'/next')->assertNoContent();
});

it('stops an old bridge when the secret address is renewed', function () {
    [$r, $owner, $d] = opsShop();
    $old = app(TicketPrinter::class)->token($r);
    $this->actingAs($owner)->post(route('orders.settings.print-token'))->assertRedirect();
    $new = app(TicketPrinter::class)->token($r);
    expect($new)->not->toBe($old);
    $this->getJson("/print/{$old}/next")->assertNotFound();
    $this->getJson("/print/{$new}/next")->assertNoContent();
    $this->actingAs($owner)->get(route('orders.settings'))->assertOk()->assertSee($new, false);
    $this->actingAs(opsUser($r, Permissions::WAITER))->post(route('orders.settings.print-token'))->assertForbidden();
});

it('queues a ticket by hand from the order page', function () {
    [$r, $owner, $d] = opsShop();
    $order = opsOrder($r, $d);
    $this->actingAs($owner)->post(route('orders.print', $order->id), ['kind' => 'receipt'])->assertRedirect();
    $this->actingAs($owner)->post(route('orders.print', $order->id), ['kind' => 'kitchen'])->assertRedirect();
    $this->actingAs($owner)->post(route('orders.print', $order->id), ['kind' => 'poster'])->assertSessionHasErrors('kind');
    expect(app(TenantContext::class)->runAs($r, fn () => PrintJob::pluck('kind')->all()))->toBe(['receipt', 'kitchen']);
    $this->actingAs($owner)->get(route('orders.show', $order->id))->assertOk()->assertSee(__('orders.print_kitchen'));
});

it('saves the new operations settings', function () {
    [$r, $owner] = opsShop();
    $this->actingAs($owner)->put(route('orders.settings.update'), ['dine_in' => 1, 'pay_cash' => 1, 'prep_minutes' => 15, 'alert_unaccepted' => 5, 'escalate_minutes' => 10, 'print_auto_kitchen' => 1, 'print_width' => 32, 'print_codepage' => 'cp857'])->assertRedirect();
    $s = app(OrderSettings::class)->for($r->fresh());
    expect([$s['alert_unaccepted'], $s['escalate_minutes'], $s['print_auto_kitchen'], $s['print_width'], $s['print_codepage']])->toBe([5, 10, true, 32, 'cp857']);
    $this->actingAs($owner)->put(route('orders.settings.update'), ['dine_in' => 1, 'pay_cash' => 1, 'print_width' => 99])->assertSessionHasErrors('print_width');
});

it('lets staff install the staff screens like an app', function () {
    [$r, $owner] = opsShop();
    $m = $this->actingAs($owner)->get(route('staff.manifest'))->assertOk()->json();
    expect($m['name'])->toContain('Ops Bistro')->and($m['start_url'])->toBe(route('orders.board'))->and($m['display'])->toBe('standalone')->and($m['shortcuts'])->toHaveCount(3);
    expect($this->actingAs($owner)->get(route('staff.icon', 192))->assertOk()->headers->get('content-type'))->toBe('image/png');
    $this->actingAs($owner)->get(route('staff.icon', 77))->assertNotFound();
    $this->actingAs($owner)->get(route('tables.index'))->assertSee('staff.webmanifest', false);
    auth()->logout();
    $this->get(route('staff.manifest'))->assertRedirect(route('login'));
});
