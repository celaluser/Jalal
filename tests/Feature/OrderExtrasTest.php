<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\OptionGroup;
use App\Modules\Menu\Models\Product;
use App\Modules\Messaging\Services\Messenger;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PushSubscription;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\OrderSettings;
use App\Modules\Orders\Services\PushNotifier;
use App\Modules\Orders\Services\WaitEstimate;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    Cache::flush();
});

function oxShop(array $settings = [], string $role = Permissions::OWNER): array
{
    $r = Restaurant::create(['name' => 'Extras Bistro', 'slug' => 'ox'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now(), 'order_settings' => $settings]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $user = User::factory()->create(['restaurant_id' => $r->id]);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($r->id);
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $d = app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Food'], 'sort' => 1]);

        return [
            'pizza' => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Pizza'], 'price' => 10, 'sort' => 1, 'station' => 'Kitchen']),
            'cola' => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Cola'], 'price' => 3, 'sort' => 2, 'station' => 'Bar']),
            't1' => DiningTable::create(['name' => 'T1']), 't2' => DiningTable::create(['name' => 'T2']),
        ];
    });

    return [$r, $user, $d];
}

function oxPlace(Restaurant $r, array $d, array $extra = [], string $source = 'qr'): Order
{
    $base = ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => [['product_id' => $d['pizza']->id, 'qty' => 1]]];

    return app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, $extra + $base, $source));
}

function oxFails(Restaurant $r, array $d, array $extra, string $reason): void
{
    try {
        oxPlace($r, $d, $extra);
        throw new RuntimeException("expected {$reason}");
    } catch (OrderException $e) {
        expect($e->reason)->toBe($reason);
    }
}

it('takes curbside orders with the car and room service orders with the room', function () {
    [$r, , $d] = oxShop(['curbside' => true, 'room_service' => true]);

    oxFails($r, $d, ['type' => 'curbside'], 'vehicle_required');
    expect(oxPlace($r, $d, ['type' => 'curbside', 'vehicle' => 'Blue Fiat'])->vehicle)->toBe('Blue Fiat');

    oxFails($r, $d, ['type' => 'curbside', 'vehicle' => 'Blue Fiat', 'customer_phone' => null], 'phone_required');
    oxFails($r, $d, ['type' => 'room_service', 'customer_phone' => null], 'room_required');
    $room = oxPlace($r, $d, ['type' => 'room_service', 'room' => '412', 'customer_phone' => null]);
    expect($room->room)->toBe('412')->and($room->vehicle)->toBeNull();
});

it('refuses the new types until the restaurant switches them on', function () {
    [$r, , $d] = oxShop();
    oxFails($r, $d, ['type' => 'curbside', 'vehicle' => 'Car'], 'type_unavailable');
    oxFails($r, $d, ['type' => 'room_service', 'room' => '1'], 'type_unavailable');
});

it('adds a packaging fee to packed orders only', function () {
    [$r, , $d] = oxShop(['takeaway' => true, 'curbside' => true, 'packaging_fee' => '0.50', 'packaging_per_item' => '0.25']);
    $take = oxPlace($r, $d, ['lines' => [['product_id' => $d['pizza']->id, 'qty' => 2]]]);
    expect($take->packaging_cents)->toBe(100)->and($take->total_cents)->toBe(2000 + 100);

    $dine = oxPlace($r, $d, ['type' => 'dine_in', 'table_id' => $d['t1']->id]);
    expect($dine->packaging_cents)->toBe(0);

    // The guest sees it before ordering.
    $quote = $this->postJson('/r/'.$r->slug.'/cart/quote', ['lines' => [['product_id' => $d['pizza']->id, 'qty' => 2]], 'type' => 'takeaway'])->assertOk();
    expect($quote->json('totals.raw.packaging'))->toBe(100);
});

it('accepts pre-orders inside the allowed window only', function () {
    [$r, , $d] = oxShop(['schedule_orders' => true, 'schedule_lead' => 30, 'schedule_days' => 2]);
    $ok = oxPlace($r, $d, ['scheduled_for' => now()->addHours(3)->toIso8601String()]);
    expect($ok->scheduled_for->diffInMinutes(now()->addHours(3)))->toBeLessThan(2);

    oxFails($r, $d, ['scheduled_for' => now()->addMinutes(5)->toIso8601String()], 'schedule_invalid');
    oxFails($r, $d, ['scheduled_for' => now()->addDays(9)->toIso8601String()], 'schedule_invalid');
    oxFails($r, $d, ['scheduled_for' => 'not a date'], 'schedule_invalid');

    [$r2, , $d2] = oxShop();
    oxFails($r2, $d2, ['scheduled_for' => now()->addHours(3)->toIso8601String()], 'schedule_invalid');
    // Staff can book any future time.
    expect(oxPlace($r2, $d2, ['scheduled_for' => now()->addMinutes(5)->toIso8601String()], 'staff')->scheduled_for)->not->toBeNull();
});

it('limits the items in a guest order but not in a staff order', function () {
    [$r, , $d] = oxShop(['max_items' => 3]);
    oxFails($r, $d, ['lines' => [['product_id' => $d['pizza']->id, 'qty' => 4]]], 'too_many_items');
    expect(oxPlace($r, $d, ['lines' => [['product_id' => $d['pizza']->id, 'qty' => 3]]])->id)->toBeInt();
    expect(oxPlace($r, $d, ['lines' => [['product_id' => $d['pizza']->id, 'qty' => 9]]], 'staff')->id)->toBeInt();
});

it('lengthens the waiting time with the kitchen queue', function () {
    [$r, , $d] = oxShop(['prep_minutes' => 10, 'wait_per_order' => 2]);
    $estimate = fn () => app(TenantContext::class)->runAs($r, fn () => app(WaitEstimate::class)->for($r->fresh()));
    expect($estimate()['minutes'])->toBe(10);

    oxPlace($r, $d);
    oxPlace($r, $d);
    expect($estimate())->toMatchArray(['minutes' => 14, 'queue' => 2]);
    // A pre-order for later does not hold up the kitchen now.
    oxPlace($r, $d, ['scheduled_for' => now()->addHours(5)->toIso8601String()], 'staff');
    expect($estimate()['queue'])->toBe(2);

    $third = oxPlace($r, $d);
    expect($third->prep_minutes)->toBe(14);
    // Off by default.
    [$r2, , $d2] = oxShop(['prep_minutes' => 10]);
    oxPlace($r2, $d2);
    expect(oxPlace($r2, $d2)->prep_minutes)->toBe(10);
});

it('puts orders of one table sitting on a shared tab', function () {
    [$r, , $d] = oxShop();
    $first = oxPlace($r, $d, ['type' => 'dine_in', 'table_id' => $d['t1']->id, 'customer_name' => 'Ann', 'customer_phone' => null]);
    $second = oxPlace($r, $d, ['type' => 'dine_in', 'table_id' => $d['t1']->id, 'customer_name' => 'Ben', 'customer_phone' => null]);
    $other = oxPlace($r, $d, ['type' => 'dine_in', 'table_id' => $d['t2']->id, 'customer_name' => 'Cy', 'customer_phone' => null]);
    expect($second->tab_id)->toBe($first->id)->and($first->fresh()->tab_id)->toBe($first->id)->and($other->tab_id)->toBeNull();

    // The guest page lists the table's bill without names or phone numbers.
    $this->get('/r/'.$r->slug.'/t/'.$d['t1']->token)->assertRedirect();
    $tab = $this->getJson('/r/'.$r->slug.'/tab')->assertOk();
    expect($tab->json('orders'))->toHaveCount(2)->and($tab->json('total'))->toBe('$20.00')->and(json_encode($tab->json()))->not->toContain('Ann')->not->toContain('555');

    app(TenantContext::class)->runAs($r, fn () => Order::find($first->id)->forceFill(['paid_at' => now()])->save());
    expect($this->getJson('/r/'.$r->slug.'/tab')->json('orders'))->toHaveCount(1);
    $this->flushSession();
    $this->getJson('/r/'.$r->slug.'/tab')->assertForbidden();
});

it('lets a seated guest call the waiter and staff clear the request', function () {
    [$r, $waiter, $d] = oxShop([], Permissions::WAITER);
    $base = '/r/'.$r->slug;
    $this->postJson($base.'/request', ['kind' => 'waiter'])->assertForbidden(); // did not scan a table
    $this->get($base.'/t/'.$d['t1']->token);
    $this->postJson($base.'/request', ['kind' => 'waiter'])->assertOk();
    $this->postJson($base.'/request', ['kind' => 'waiter'])->assertOk(); // same thing again: not a duplicate
    $this->postJson($base.'/request', ['kind' => 'bill'])->assertOk();
    $this->postJson($base.'/request', ['kind' => 'valet'])->assertOk();
    $this->postJson($base.'/request', ['kind' => 'dance'])->assertStatus(422);

    $feed = $this->actingAs($waiter)->getJson(route('orders.feed'))->json('requests');
    expect($feed)->toHaveCount(3)->and($feed[0]['table'])->toBe('T1')->and($feed[0]['kind'])->toBe('waiter');

    $this->actingAs($waiter)->postJson(route('orders.requests.done', $feed[0]['id']))->assertOk();
    expect($this->actingAs($waiter)->getJson(route('orders.feed'))->json('requests'))->toHaveCount(2);
});

it('sends a delivery out and tells the guest, once', function () {
    [$r, $owner, $d] = oxShop(['delivery' => true]);
    $order = oxPlace($r, $d, ['type' => 'delivery', 'delivery_address' => '1 Long Street']);
    $this->actingAs($owner)->postJson(route('orders.dispatch', $order->id))->assertStatus(422); // not ready yet

    foreach (['accepted', 'preparing', 'ready'] as $to) {
        $this->actingAs($owner)->postJson(route('orders.status', $order->id), ['status' => $to])->assertOk();
    }
    $this->actingAs($owner)->postJson(route('orders.dispatch', $order->id))->assertOk();
    $this->actingAs($owner)->postJson(route('orders.dispatch', $order->id))->assertStatus(422);
    $order = app(TenantContext::class)->runAs($r, fn () => Order::find($order->id));
    expect($order->dispatched_at)->not->toBeNull()->and($order->status)->toBe('ready');

    $this->getJson('/r/'.$r->slug.'/order/'.$order->token.'/status')->assertJsonPath('dispatched', true);

    $pickup = oxPlace($r, $d);
    app(TenantContext::class)->runAs($r, fn () => $pickup->forceFill(['status' => 'ready'])->save());
    $this->actingAs($owner)->postJson(route('orders.dispatch', $pickup->id))->assertStatus(422);
});

it('texts a guest who asked, when the order is ready and when it is on the way', function () {
    [$r, $owner, $d] = oxShop(['delivery' => true, 'notify_sms' => true]);
    $sent = [];
    $this->mock(Messenger::class, function ($m) use (&$sent) {
        $m->shouldReceive('available')->andReturn(true);
        $m->shouldReceive('send')->andReturnUsing(function ($rest, $channel, $to, $text) use (&$sent) {
            $sent[] = [$channel, $to, $text];

            return true;
        });
    });

    $order = oxPlace($r, $d, ['type' => 'delivery', 'delivery_address' => '1 Long Street', 'notify' => 'sms']);
    expect($order->notify_channel)->toBe('sms');
    foreach (['accepted', 'preparing', 'ready'] as $to) {
        $this->actingAs($owner)->postJson(route('orders.status', $order->id), ['status' => $to])->assertOk();
    }
    $this->actingAs($owner)->postJson(route('orders.dispatch', $order->id))->assertOk();

    expect($sent)->toHaveCount(2)->and($sent[0][0])->toBe('sms')->and($sent[0][1])->toBe('+1 555 111 2222')
        ->and($sent[0][2])->toContain('Extras Bistro')->toContain('#'.$order->number)->and($sent[1][2])->toContain('on the way');
});

it('only records a notification channel the restaurant offers and a number backs', function () {
    [$r, , $d] = oxShop(['notify_sms' => true]);
    expect(oxPlace($r, $d, ['notify' => 'whatsapp'])->notify_channel)->toBeNull()
        ->and(oxPlace($r, $d, ['notify' => 'sms', 'customer_phone' => null, 'type' => 'dine_in', 'table_id' => $d['t1']->id])->notify_channel)->toBeNull()
        ->and(oxPlace($r, $d, ['notify' => 'sms'])->notify_channel)->toBe('sms');
});

it('sends browser push to a subscribed guest and drops dead subscriptions', function () {
    [$r, $owner, $d] = oxShop(['notify_push' => true]);
    $order = oxPlace($r, $d);
    $base = '/r/'.$r->slug.'/order/'.$order->token;

    $key = app(PushNotifier::class)->publicKey();
    expect($key)->not->toBeNull()->and(app(PushNotifier::class)->publicKey())->toBe($key); // made once, then kept
    $this->getJson($base.'/status')->assertJsonPath('push_key', $key);

    $this->postJson($base.'/push', ['endpoint' => 'http://insecure.example/x', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertStatus(422);
    $this->postJson($base.'/push', ['endpoint' => 'https://push.example/abc', 'keys' => ['p256dh' => 'BNc', 'auth' => 'xyz']])->assertOk();
    expect(app(TenantContext::class)->runAs($r, fn () => [PushSubscription::count(), Order::find($order->id)->notify_channel]))->toBe([1, 'push']);
    $this->getJson($base.'/status')->assertJsonPath('push_key', null);

    // The listener hands the message to the notifier.
    $calls = [];
    $this->mock(PushNotifier::class, function ($m) use (&$calls) {
        $m->shouldReceive('send')->andReturnUsing(function ($o, $title, $body, $url) use (&$calls) {
            $calls[] = [$title, $body, $url];

            return true;
        });
    });
    foreach (['accepted', 'preparing', 'ready'] as $to) {
        $this->actingAs($owner)->postJson(route('orders.status', $order->id), ['status' => $to])->assertOk();
    }
    expect($calls)->toHaveCount(1)->and($calls[0][0])->toBe('Extras Bistro')->and($calls[0][2])->toContain($order->token);
});

it('does not take push subscriptions for finished orders', function () {
    [$r, , $d] = oxShop(['notify_push' => true]);
    $order = oxPlace($r, $d);
    app(TenantContext::class)->runAs($r, fn () => $order->forceFill(['status' => 'completed'])->save());
    $this->postJson('/r/'.$r->slug.'/order/'.$order->token.'/push', ['endpoint' => 'https://push.example/abc', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertStatus(422);
});

it('gives back the lines of a past order for ordering again', function () {
    [$r, , $d] = oxShop();
    $order = oxPlace($r, $d, ['lines' => [['product_id' => $d['pizza']->id, 'qty' => 2, 'note' => 'well done'], ['product_id' => $d['cola']->id, 'qty' => 1]]]);
    $lines = $this->getJson('/r/'.$r->slug.'/order/'.$order->token.'/reorder')->assertOk()->json('lines');
    expect($lines)->toHaveCount(2)->and($lines[0])->toMatchArray(['product_id' => $d['pizza']->id, 'qty' => 2, 'note' => 'well done']);
    $this->getJson('/r/'.$r->slug.'/order/'.str_repeat('z', 24).'/reorder')->assertNotFound();

    // The status page of a finished order offers it.
    app(TenantContext::class)->runAs($r, fn () => $order->forceFill(['status' => 'completed'])->save());
    $this->get('/r/'.$r->slug.'/order/'.$order->token)->assertOk()->assertSee('?reorder='.$order->token, false);
});

it('keeps the options, size and set-menu picks for ordering again', function () {
    [$r, , $d] = oxShop();
    $opt = app(TenantContext::class)->runAs($r, function () use ($d) {
        $g = OptionGroup::create(['name' => ['en' => 'Extras'], 'type' => 'multiple']);
        $o = $g->options()->create(['name' => ['en' => 'Cheese'], 'price_delta' => 1, 'sort' => 1]);
        $d['pizza']->optionGroups()->attach($g->id, ['sort' => 0]);

        return $o;
    });
    $order = oxPlace($r, $d, ['lines' => [['product_id' => $d['pizza']->id, 'qty' => 1, 'options' => [$opt->id]]]]);
    expect($this->getJson('/r/'.$r->slug.'/order/'.$order->token.'/reorder')->json('lines.0.options'))->toBe([$opt->id]);
});

it('adds up open orders on the prep list and filters by station', function () {
    [$r, $owner, $d] = oxShop(['stations' => 'Kitchen, Bar']);
    oxPlace($r, $d, ['lines' => [['product_id' => $d['pizza']->id, 'qty' => 2], ['product_id' => $d['cola']->id, 'qty' => 1]]]);
    oxPlace($r, $d, ['lines' => [['product_id' => $d['pizza']->id, 'qty' => 3]]]);
    $done = oxPlace($r, $d, ['lines' => [['product_id' => $d['pizza']->id, 'qty' => 9]]]);
    app(TenantContext::class)->runAs($r, fn () => $done->forceFill(['status' => 'completed'])->save());

    $page = $this->actingAs($owner)->get(route('orders.batch'))->assertOk();
    $page->assertSeeInOrder(['5×', 'Pizza'])->assertDontSee('14×');
    $this->actingAs($owner)->get(route('orders.batch', ['station' => 'Bar']))->assertOk()->assertSee('Cola')->assertDontSee('Pizza');
    $this->actingAs($owner)->get(route('orders.batch', ['station' => 'Nonsense']))->assertOk()->assertSee('Pizza');
});

it('assigns a dish to a station only from the restaurant’s list', function () {
    [$r, $owner, $d] = oxShop(['stations' => 'Kitchen, Bar']);
    $body = ['name' => ['en' => 'Pizza'], 'category_id' => $d['pizza']->category_id, 'price' => 10, 'is_active' => 1, 'is_available' => 1];
    $this->actingAs($owner)->put(route('menu.products.update', $d['pizza']->id), $body + ['station' => 'Bar'])->assertRedirect();
    expect(app(TenantContext::class)->runAs($r, fn () => Product::find($d['pizza']->id)->station))->toBe('Bar');
    $this->actingAs($owner)->put(route('menu.products.update', $d['pizza']->id), $body + ['station' => 'Roof'])->assertRedirect();
    expect(app(TenantContext::class)->runAs($r, fn () => Product::find($d['pizza']->id)->station))->toBeNull();

    // The station is stamped on the order lines at the time of ordering.
    app(TenantContext::class)->runAs($r, fn () => Product::find($d['pizza']->id)->update(['station' => 'Kitchen']));
    expect(app(TenantContext::class)->runAs($r, fn () => oxPlace($r, $d)->items()->first()->station))->toBe('Kitchen');
});

it('saves the new ordering settings and keeps bad station names out', function () {
    [$r, $owner] = oxShop();
    $base = ['dine_in' => 1, 'pay_cash' => 1, 'prep_minutes' => 15];
    $this->actingAs($owner)->put(route('orders.settings.update'), $base + ['curbside' => 1, 'room_service' => 1, 'packaging_fee' => '1.20', 'max_items' => 20, 'schedule_orders' => 1, 'schedule_lead' => 45, 'stations' => 'Kitchen, Bar', 'notify_push' => 1])->assertRedirect();
    $s = app(OrderSettings::class)->for($r->fresh());
    expect([$s['curbside'], $s['room_service'], $s['packaging_fee'], $s['max_items'], $s['schedule_lead'], $s['notify_push']])->toBe([true, true, '1.20', 20, 45, true]);

    $this->actingAs($owner)->put(route('orders.settings.update'), $base + ['stations' => '<script>x</script>'])->assertSessionHasErrors('stations');
    // Only curbside on is a valid way to take orders.
    $this->actingAs($owner)->put(route('orders.settings.update'), ['curbside' => 1, 'pay_cash' => 1])->assertSessionHasNoErrors();
    $this->actingAs($owner)->put(route('orders.settings.update'), ['pay_cash' => 1])->assertSessionHasErrors('dine_in');
});

it('shows the new types at the till and on the board', function () {
    [$r, $owner, $d] = oxShop(['curbside' => true]);
    $this->actingAs($owner)->get(route('orders.pos.index'))->assertOk()->assertSee(__('orders.type_curbside'))->assertSee(__('orders.type_room_service'));
    $this->actingAs($owner)->postJson(route('orders.pos.store'), ['type' => 'curbside', 'vehicle' => 'Red van', 'customer_name' => 'Walk', 'customer_phone' => '+1 555 000 1111', 'lines' => [['product_id' => $d['pizza']->id, 'qty' => 1]]])->assertCreated();
    $row = $this->actingAs($owner)->getJson(route('orders.feed'))->json('orders.0');
    expect($row['type'])->toBe('curbside')->and($row['vehicle'])->toBe('Red van');
    $this->actingAs($owner)->get(route('orders.board'))->assertOk()->assertSee(__('orders.batch_title'));
});

it('renders the guest checkout with the new fields', function () {
    [$r] = oxShop(['curbside' => true, 'schedule_orders' => true, 'notify_sms' => true, 'max_items' => 5]);
    $html = $this->get('/r/'.$r->slug)->assertOk()->assertSee(__('orders.vehicle_label'))->assertSee(__('orders.room_label'))->assertSee(__('orders.schedule_asap'))->getContent();
    expect($html)->toContain('\\u0022maxItems\\u0022:5')->toContain('\\u0022curbside\\u0022');
});
