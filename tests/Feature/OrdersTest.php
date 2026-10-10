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
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\OrderSettings;
use App\Modules\Orders\Services\OrderTotals;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
});

/** A restaurant with a plan, one table, and a menu of a pizza (with a size option), a soda and a sold-out cake. */
function odShop(array $settings = [], array $attributes = []): array
{
    $r = Restaurant::create(array_merge(['name' => 'Bella', 'slug' => 'bella'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'onboarded_at' => now(), 'order_settings' => $settings], $attributes));
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());

    $data = app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Food'], 'sort' => 1]);
        $pizza = Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Pizza'], 'price' => 10, 'sort' => 1]);
        $soda = Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Soda'], 'price' => 2.5, 'sort' => 2]);
        $cake = Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Cake'], 'price' => 5, 'sort' => 3, 'is_available' => false]);
        $size = OptionGroup::create(['name' => ['en' => 'Size'], 'type' => 'single', 'is_required' => true]);
        $regular = $size->options()->create(['name' => ['en' => 'Regular'], 'price_delta' => 0, 'sort' => 1]);
        $large = $size->options()->create(['name' => ['en' => 'Large'], 'price_delta' => 3, 'sort' => 2]);
        $pizza->optionGroups()->attach($size->id, ['sort' => 0]);

        return ['pizza' => $pizza, 'soda' => $soda, 'cake' => $cake, 'regular' => $regular, 'large' => $large, 'table' => DiningTable::create(['name' => 'T1'])];
    });

    return [$r, $data];
}

function odLines(array $d, int $qty = 1): array
{
    return [['product_id' => $d['pizza']->id, 'qty' => $qty, 'options' => [$d['large']->id]], ['product_id' => $d['soda']->id, 'qty' => 2]];
}

function odPlace(Restaurant $r, array $d, array $over = [], string $source = 'qr'): Order
{
    return app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, array_merge([
        'type' => 'dine_in', 'table_id' => $d['table']->id, 'payment_method' => 'cash', 'lines' => odLines($d),
    ], $over), $source)->load('items')); // loaded here: relations cannot be read lazily outside the tenant context
}

function odUser(Restaurant $r, string $role): User
{
    $user = User::factory()->create(['restaurant_id' => $r->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return $user;
}

function odIn(Restaurant $r, Closure $fn): mixed
{
    return app(TenantContext::class)->runAs($r, $fn);
}

function odReason(Restaurant $r, array $d, array $over): string
{
    try {
        odPlace($r, $d, $over);
    } catch (OrderException $e) {
        return $e->reason;
    }

    return 'accepted';
}

describe('placing an order', function () {
    it('stores a priced order with a snapshot of what was sold', function () {
        Event::fake([OrderPlaced::class]);
        [$r, $d] = odShop();

        $order = odPlace($r, $d, ['customer_name' => '  Ada ', 'note' => '<b>no</b> onions']);

        expect($order->number)->toBe(1001)->and($order->status)->toBe('new')->and($order->type)->toBe('dine_in')->and($order->table_name)->toBe('T1')
            ->and($order->token)->toMatch('/^[a-z0-9]{24}$/')->and($order->subtotal_cents)->toBe(1800)->and($order->total_cents)->toBe(1800)
            ->and($order->customer_name)->toBe('Ada')->and($order->note)->toBe('no onions')->and($order->currency_code)->toBe('USD')->and($order->source)->toBe('qr')
            ->and($order->items)->toHaveCount(2)->and($order->items[0]->name)->toBe('Pizza')->and($order->items[0]->unit_cents)->toBe(1300)
            ->and($order->items[0]->options)->toBe([['group' => 'Size', 'name' => 'Large', 'price_delta_cents' => 300]])
            ->and($order->items[1]->total_cents)->toBe(500)
            ->and(odIn($r, fn () => $order->events()->pluck('type')->all()))->toBe(['placed']);
        Event::assertDispatched(OrderPlaced::class);
    });

    it('numbers orders one after another per restaurant', function () {
        [$a, $da] = odShop();
        [$b, $db] = odShop();

        expect([odPlace($a, $da)->number, odPlace($a, $da)->number, odPlace($b, $db)->number])->toBe([1001, 1002, 1001]);
    });

    it('keeps past orders unchanged when the menu changes', function () {
        [$r, $d] = odShop();
        $order = odPlace($r, $d);

        odIn($r, fn () => $d['pizza']->update(['name' => ['en' => 'Renamed'], 'price' => 99]));
        odIn($r, fn () => $d['soda']->delete());

        $order = odIn($r, fn () => $order->fresh()->load('items'));
        expect($order->items[0]->name)->toBe('Pizza')->and($order->items[0]->unit_cents)->toBe(1300)->and($order->total_cents)->toBe(1800);
    });

    it('ignores prices and names sent by the browser', function () {
        [$r, $d] = odShop();

        $order = odPlace($r, $d, ['lines' => [['product_id' => $d['soda']->id, 'qty' => 1, 'unit_cents' => 1, 'price' => 0.01, 'name' => 'Free soda']]]);

        expect($order->total_cents)->toBe(250)->and($order->items[0]->name)->toBe('Soda');
    });

    it('refuses carts with unavailable or invalid lines', function () {
        [$r, $d] = odShop();

        foreach ([
            [['product_id' => $d['cake']->id, 'qty' => 1]],                          // sold out
            [['product_id' => $d['pizza']->id, 'qty' => 1]],                         // required size missing
            [['product_id' => 999999, 'qty' => 1]],                                  // unknown
            [],                                                                       // empty
        ] as $lines) {
            expect(odReason($r, $d, ['lines' => $lines]))->toBe('cart_invalid');
        }

        $e = null;
        try {
            odPlace($r, $d, ['lines' => [['product_id' => $d['cake']->id, 'qty' => 1]]]);
        } catch (OrderException $e) {
        }
        expect($e->details[0]['errors'])->toContain('sold_out')->and(odIn($r, fn () => Order::count()))->toBe(0);
    });

    it('refuses everything while ordering is paused or nothing can be ordered', function () {
        [$r, $d] = odShop(['enabled' => false]);
        expect(odReason($r, $d, []))->toBe('closed');

        [$r2, $d2] = odShop(['dine_in' => false, 'takeaway' => false, 'delivery' => false]);
        expect(odReason($r2, $d2, []))->toBe('closed');

        [$r3, $d3] = odShop(['pay_cash' => false, 'pay_card' => false]);
        expect(odReason($r3, $d3, []))->toBe('closed');
    });

    it('only takes enabled order types and payment methods', function () {
        [$r, $d] = odShop(['delivery' => false, 'pay_card' => false]);

        expect(odReason($r, $d, ['type' => 'delivery', 'customer_name' => 'A', 'customer_phone' => '555 1234 567', 'delivery_address' => '1 Long Street']))->toBe('type_unavailable')
            ->and(odReason($r, $d, ['type' => 'nonsense']))->toBe('type_unavailable')
            ->and(odReason($r, $d, ['payment_method' => 'card']))->toBe('payment_unavailable')
            ->and(odReason($r, $d, ['payment_method' => 'bitcoin']))->toBe('payment_unavailable')
            ->and(odReason($r, $d, ['payment_method' => null]))->toBe('payment_unavailable');
    });

    it('needs a real active table of this restaurant for dine in', function () {
        [$r, $d] = odShop();
        [$other, $do] = odShop();
        odIn($r, fn () => $d['table']->update(['is_active' => false]));

        expect(odReason($r, $d, []))->toBe('table_required')
            ->and(odReason($r, $d, ['table_id' => null]))->toBe('table_required')
            ->and(odReason($r, $d, ['table_id' => $do['table']->id]))->toBe('table_required'); // a table of another restaurant
    });

    it('asks takeaway and delivery guests for contact details', function () {
        [$r, $d] = odShop(['delivery' => true]);
        $ok = ['customer_name' => 'Ada', 'customer_phone' => '+90 555 123 45 67'];

        expect(odReason($r, $d, ['type' => 'takeaway']))->toBe('phone_required')
            ->and(odReason($r, $d, ['type' => 'takeaway', 'customer_phone' => 'call me']))->toBe('phone_required')
            ->and(odReason($r, $d, ['type' => 'takeaway', 'customer_phone' => '+90 555 123 45 67']))->toBe('name_required')
            ->and(odReason($r, $d, ['type' => 'takeaway'] + $ok))->toBe('accepted')
            ->and(odReason($r, $d, ['type' => 'delivery'] + $ok))->toBe('address_required')
            ->and(odReason($r, $d, ['type' => 'delivery', 'delivery_address' => 'abc'] + $ok))->toBe('address_required')
            ->and(odReason($r, $d, ['type' => 'delivery', 'delivery_address' => '12 Long Street'] + $ok))->toBe('accepted');
    });

    it('can ask dine-in guests for a name', function () {
        [$r, $d] = odShop(['require_name' => true]);

        expect(odReason($r, $d, []))->toBe('name_required')->and(odReason($r, $d, ['customer_name' => 'Ada']))->toBe('accepted');
    });

    it('applies the delivery minimum, fee, service charge and tax', function () {
        [$r, $d] = odShop(['delivery' => true, 'delivery_min' => '20', 'delivery_fee' => '3.50', 'tax_rate' => '10', 'prices_include_tax' => false, 'service_rate' => '10']);
        $contact = ['customer_name' => 'Ada', 'customer_phone' => '555 123 4567', 'delivery_address' => '12 Long Street'];

        expect(odReason($r, $d, ['type' => 'delivery'] + $contact))->toBe('below_minimum');

        $delivery = odPlace($r, $d, ['type' => 'delivery', 'lines' => odLines($d, 2)] + $contact);   // 2 x 13.00 + 2 x 2.50 = 31.00
        expect($delivery->subtotal_cents)->toBe(3100)->and($delivery->service_cents)->toBe(0)->and($delivery->delivery_cents)->toBe(350)
            ->and($delivery->tax_cents)->toBe(310)->and($delivery->total_cents)->toBe(3760);

        $dineIn = odPlace($r, $d);                                                                   // 18.00 + 1.80 service, 10% tax on 19.80
        expect($dineIn->service_cents)->toBe(180)->and($dineIn->delivery_cents)->toBe(0)->and($dineIn->tax_cents)->toBe(198)->and($dineIn->total_cents)->toBe(2178);
    });

    it('computes totals exactly', function (int $subtotal, string $type, array $settings, array $expected) {
        $settings = array_replace(OrderSettings::DEFAULTS, $settings);

        expect(app(OrderTotals::class)->compute($subtotal, $type, $settings))->toEqual(['discount' => 0, 'packaging' => 0] + $expected);
    })->with([
        'nothing extra' => [1000, 'dine_in', [], ['subtotal' => 1000, 'service' => 0, 'delivery' => 0, 'tax' => 0, 'total' => 1000]],
        'tax included' => [1100, 'takeaway', ['tax_rate' => '10', 'prices_include_tax' => true], ['subtotal' => 1100, 'service' => 0, 'delivery' => 0, 'tax' => 100, 'total' => 1100]],
        'tax added' => [1000, 'takeaway', ['tax_rate' => '8', 'prices_include_tax' => false], ['subtotal' => 1000, 'service' => 0, 'delivery' => 0, 'tax' => 80, 'total' => 1080]],
        'service only dine in' => [1000, 'takeaway', ['service_rate' => '12'], ['subtotal' => 1000, 'service' => 0, 'delivery' => 0, 'tax' => 0, 'total' => 1000]],
        'service rounds' => [333, 'dine_in', ['service_rate' => '10'], ['subtotal' => 333, 'service' => 33, 'delivery' => 0, 'tax' => 0, 'total' => 366]],
        'delivery fee' => [1000, 'delivery', ['delivery_fee' => '2.99'], ['subtotal' => 1000, 'service' => 0, 'delivery' => 299, 'tax' => 0, 'total' => 1299]],
    ]);

    it('returns the same order for a repeated idempotency key', function () {
        [$r, $d] = odShop();

        $a = odPlace($r, $d, ['idempotency_key' => 'abc123']);
        $b = odPlace($r, $d, ['idempotency_key' => 'abc123']);
        $c = odPlace($r, $d, ['idempotency_key' => 'other']);

        expect($b->id)->toBe($a->id)->and($c->id)->not->toBe($a->id)->and(odIn($r, fn () => Order::count()))->toBe(2);
    });

    it('can accept orders automatically', function () {
        [$r, $d] = odShop(['auto_accept' => true, 'prep_minutes' => 20]);

        $order = odPlace($r, $d);

        expect($order->status)->toBe('accepted')->and($order->accepted_at)->not->toBeNull()->and($order->prep_minutes)->toBe(20)
            ->and(odIn($r, fn () => $order->events()->pluck('to')->all()))->toBe([OrderStatus::NEW, OrderStatus::ACCEPTED]);
    });

    it('never mass assigns identity or status', function () {
        $order = new Order(['number' => 5, 'token' => 'x', 'status' => 'completed', 'restaurant_id' => 9, 'note' => 'ok']);

        expect($order->number)->toBeNull()->and($order->token)->toBeNull()->and($order->status)->toBeNull()->and($order->restaurant_id)->toBeNull()->and($order->note)->toBe('ok');
    });

    it('keeps orders private to their restaurant', function () {
        [$a, $da] = odShop();
        [$b, $db] = odShop();
        odPlace($a, $da);

        expect(odIn($b, fn () => Order::count()))->toBe(0)->and(odIn($a, fn () => Order::count()))->toBe(1);
        expect(odReason($b, $db, ['lines' => [['product_id' => $da['soda']->id, 'qty' => 1]]]))->toBe('cart_invalid'); // another restaurant's product
    });
});

describe('order status flow', function () {
    it('knows the allowed moves', function () {
        expect(OrderStatus::next('new'))->toBe(['accepted', 'preparing', 'cancelled'])->and(OrderStatus::next('ready'))->toBe(['completed', 'cancelled'])
            ->and(OrderStatus::next('completed'))->toBe([])->and(OrderStatus::next('cancelled'))->toBe([])
            ->and(OrderStatus::canMove('new', 'ready'))->toBeFalse()->and(OrderStatus::forward('preparing'))->toBe('ready')->and(OrderStatus::forward('completed'))->toBeNull();
    });

    it('walks an order from new to completed, recording time and history', function () {
        Event::fake([OrderStatusChanged::class]);
        [$r, $d] = odShop();
        $order = odPlace($r, $d);
        $staff = odUser($r, Permissions::OWNER);
        $service = app(OrderService::class);

        odIn($r, function () use ($service, $order, $staff) {
            foreach (['accepted', 'preparing', 'ready', 'completed'] as $to) {
                $service->transition($order, $to, $staff);
            }
        });

        $order = odIn($r, fn () => $order->fresh());
        expect($order->status)->toBe('completed')->and($order->accepted_at)->not->toBeNull()->and($order->ready_at)->not->toBeNull()->and($order->completed_at)->not->toBeNull()
            ->and(odIn($r, fn () => $order->events()->where('type', 'status')->pluck('to')->all()))->toBe(['accepted', 'preparing', 'ready', 'completed'])
            ->and(odIn($r, fn () => $order->events()->where('type', 'status')->first()->user_id))->toBe($staff->id);
        Event::assertDispatchedTimes(OrderStatusChanged::class, 4);
    });

    it('refuses skipping steps and moving finished orders', function () {
        [$r, $d] = odShop();
        $order = odPlace($r, $d);
        $service = app(OrderService::class);

        foreach (['ready', 'completed', 'new', 'bogus'] as $to) {
            expect(fn () => odIn($r, fn () => $service->transition($order, $to)))->toThrow(OrderException::class);
        }

        odIn($r, fn () => $service->transition($order, 'cancelled', null, '<i>Out of dough</i>'));
        expect(fn () => odIn($r, fn () => $service->transition($order, 'accepted')))->toThrow(OrderException::class)
            ->and(odIn($r, fn () => $order->fresh()->cancel_reason))->toBe('Out of dough');
    });

    it('records payment once, never on cancelled orders', function () {
        [$r, $d] = odShop();
        $order = odPlace($r, $d);
        $service = app(OrderService::class);

        odIn($r, fn () => $service->markPaid($order, 'card'));
        expect($order->fresh()->isPaid())->toBeTrue()->and($order->fresh()->payment_method)->toBe('card')
            ->and(fn () => odIn($r, fn () => $service->markPaid($order, 'cash')))->toThrow(OrderException::class);

        $other = odPlace($r, $d);
        odIn($r, fn () => $service->transition($other, 'cancelled'));
        expect(fn () => odIn($r, fn () => $service->markPaid($other, 'cash')))->toThrow(OrderException::class)
            ->and(fn () => odIn($r, fn () => $service->markPaid(odPlace($r, $d), 'voucher')))->toThrow(OrderException::class);
    });
});

describe('guest checkout over http', function () {
    function odBody(array $d, array $over = []): array
    {
        return array_merge(['type' => 'dine_in', 'table_id' => $d['table']->id, 'payment_method' => 'cash', 'lines' => odLines($d)], $over);
    }

    it('places an order and returns the tracking address', function () {
        [$r, $d] = odShop();

        $res = $this->postJson('/r/'.$r->slug.'/order', odBody($d))->assertCreated();

        $order = odIn($r, fn () => Order::first());
        $res->assertJsonPath('number', 1001)->assertJsonPath('token', $order->token)->assertJsonPath('url', url('/r/'.$r->slug.'/order/'.$order->token));
        expect($order->total_cents)->toBe(1800);
    });

    it('uses the scanned table and never lets a guest claim another', function () {
        [$r, $d] = odShop();
        $other = odIn($r, fn () => DiningTable::create(['name' => 'T2']));

        $this->get('/r/'.$r->slug.'/t/'.$d['table']->token);
        $this->postJson('/r/'.$r->slug.'/order', odBody($d, ['table_id' => $other->id]))->assertCreated();

        expect(odIn($r, fn () => Order::first()->table_name))->toBe('T1');
    });

    it('lets guests pick a table without a code only when allowed', function () {
        [$r, $d] = odShop();
        $this->postJson('/r/'.$r->slug.'/order', odBody($d))->assertCreated();
        expect(odIn($r, fn () => Order::first()->table_name))->toBe('T1');

        [$r2, $d2] = odShop(['dine_in_pick_table' => false]);
        $this->postJson('/r/'.$r2->slug.'/order', odBody($d2))->assertStatus(422)->assertJsonPath('error', 'table_required');
    });

    it('explains why an order was refused', function () {
        [$r, $d] = odShop();

        $this->postJson('/r/'.$r->slug.'/order', odBody($d, ['lines' => [['product_id' => $d['cake']->id, 'qty' => 1]]]))
            ->assertStatus(422)->assertJsonPath('error', 'cart_invalid')->assertJsonPath('message', __('orders.error_cart_invalid'))->assertJsonPath('lines.0.errors.0', 'sold_out');
        $this->postJson('/r/'.$r->slug.'/order', odBody($d, ['type' => 'takeaway']))->assertStatus(422)->assertJsonPath('error', 'phone_required');
        $this->postJson('/r/'.$r->slug.'/order', ['type' => 'x'])->assertStatus(422)->assertJsonValidationErrors(['type', 'lines', 'payment_method']);
        expect(odIn($r, fn () => Order::count()))->toBe(0);
    });

    it('does not double order on a retry with the same key', function () {
        [$r, $d] = odShop();

        $a = $this->postJson('/r/'.$r->slug.'/order', odBody($d, ['idempotency_key' => 'k1']))->assertCreated()->json('token');
        $b = $this->postJson('/r/'.$r->slug.'/order', odBody($d, ['idempotency_key' => 'k1']))->assertCreated()->json('token');

        expect($b)->toBe($a)->and(odIn($r, fn () => Order::count()))->toBe(1);
    });

    it('rate limits order placement', function () {
        [$r, $d] = odShop();

        foreach (range(1, 8) as $i) {
            $this->postJson('/r/'.$r->slug.'/order', odBody($d))->assertCreated();
        }

        $this->postJson('/r/'.$r->slug.'/order', odBody($d))->assertStatus(429);
    });

    it('works on a restaurants own domain', function () {
        config(['tenancy.subdomains_enabled' => true, 'tenancy.base_domain' => 'qrmenu.test']);
        [$r, $d] = odShop([], ['subdomain' => 'bella']);

        $res = $this->postJson('http://bella.qrmenu.test/order', odBody($d))->assertCreated();

        expect($res->json('url'))->toStartWith('http://bella.qrmenu.test/order/');
        $this->get($res->json('url'))->assertOk();
    });

    it('shows the status page and a safe status feed', function () {
        [$r, $d] = odShop();
        $order = odPlace($r, $d, ['customer_name' => 'Ada Secret', 'customer_phone' => '555 000 1111']);

        $this->get('/r/'.$r->slug.'/order/'.$order->token)->assertOk()->assertSee('#1001')->assertSee('noindex', false);
        $feed = $this->getJson('/r/'.$r->slug.'/order/'.$order->token.'/status')->assertOk()->assertJsonPath('status', 'new')->assertJsonPath('total', '$18.00')->assertJsonPath('items.0.name', 'Pizza');

        expect($feed->getContent())->not->toContain('Ada Secret')->not->toContain('555 000 1111');
    });

    it('hides orders behind their token and their own restaurant', function () {
        [$a, $da] = odShop();
        [$b] = odShop();
        $order = odPlace($a, $da);

        $this->get('/r/'.$a->slug.'/order/'.str_repeat('a', 24))->assertNotFound();
        $this->get('/r/'.$b->slug.'/order/'.$order->token)->assertNotFound();
        $this->getJson('/r/'.$b->slug.'/order/'.$order->token.'/status')->assertNotFound();
        $this->postJson('/r/'.$b->slug.'/order/'.$order->token.'/cancel')->assertNotFound();
        $this->get('/r/'.$a->slug.'/order/short')->assertNotFound();
    });

    it('lets a guest cancel only while the order is new', function () {
        [$r, $d] = odShop();
        $order = odPlace($r, $d);
        $url = '/r/'.$r->slug.'/order/'.$order->token;

        $this->getJson($url.'/status')->assertJsonPath('can_cancel', true);
        $this->postJson($url.'/cancel')->assertOk()->assertJsonPath('status', 'cancelled');
        expect(odIn($r, fn () => $order->fresh()->cancel_reason))->toBe(__('orders.cancelled_by_guest'));

        $accepted = odPlace($r, $d);
        odIn($r, fn () => app(OrderService::class)->transition($accepted, 'accepted'));
        $this->postJson('/r/'.$r->slug.'/order/'.$accepted->token.'/cancel')->assertStatus(422)->assertJsonPath('error', 'cannot_cancel');
    });

    it('can switch guest cancellation off', function () {
        [$r, $d] = odShop(['allow_cancel' => false]);
        $order = odPlace($r, $d);

        $this->postJson('/r/'.$r->slug.'/order/'.$order->token.'/cancel')->assertStatus(422);
        expect(odIn($r, fn () => $order->fresh()->status))->toBe('new');
    });

    it('quotes the full price for the chosen order type', function () {
        [$r, $d] = odShop(['delivery' => true, 'delivery_fee' => '4', 'tax_rate' => '10', 'prices_include_tax' => false]);

        $res = $this->postJson('/r/'.$r->slug.'/cart/quote', ['lines' => odLines($d), 'type' => 'delivery'])->assertOk();

        $res->assertJsonPath('totals.raw.subtotal', 1800)->assertJsonPath('totals.raw.delivery', 400)->assertJsonPath('totals.raw.tax', 180)->assertJsonPath('totals.raw.total', 2380)->assertJsonPath('totals.total', '$23.80');
        $this->postJson('/r/'.$r->slug.'/cart/quote', ['lines' => odLines($d)])->assertOk()->assertJsonMissingPath('totals');
        $this->postJson('/r/'.$r->slug.'/cart/quote', ['lines' => odLines($d), 'type' => 'teleport'])->assertStatus(422);
    });

    it('tells the menu page how ordering works here', function () {
        [$r] = odShop(['delivery' => true, 'delivery_min' => '15', 'pay_card' => false, 'enabled' => true]);

        $html = $this->get('/r/'.$r->slug)->getContent();

        expect($html)->toContain('accepting\\u0022:true')->toContain('dine_in')->toContain('delivery')->toContain('$15.00');
    });
});

describe('staff board', function () {
    it('is for staff who can see orders', function () {
        [$r, $d] = odShop();
        $order = odPlace($r, $d);

        $this->get('/orders')->assertRedirect(route('login'));

        foreach ([Permissions::OWNER, Permissions::MANAGER, Permissions::WAITER, Permissions::KITCHEN, Permissions::CASHIER] as $role) {
            $this->actingAs(odUser($r, $role))->get('/orders')->assertOk()->assertSee(__('orders.board_title'));
            $this->get(route('orders.show', $order->id))->assertOk();
        }
    });

    it('lists open orders and recent finished ones, only of this restaurant', function () {
        [$r, $d] = odShop();
        [$other, $do] = odShop();
        $open = odPlace($r, $d);
        $done = odPlace($r, $d);
        $old = odPlace($r, $d);
        odPlace($other, $do);
        foreach ([$done, $old] as $o) {
            odIn($r, fn () => app(OrderService::class)->transition($o, 'cancelled'));
        }
        DB::table('orders')->where('id', $old->id)->update(['updated_at' => now()->subHours(5)]);

        $feed = $this->actingAs(odUser($r, Permissions::OWNER))->getJson('/orders/feed')->assertOk()->json();

        expect(collect($feed['orders'])->pluck('number')->all())->toBe([$open->number, $done->number])->and($feed['open'])->toBe(1)->and($feed['accepting'])->toBeTrue()
            ->and($feed['orders'][0]['items'][0])->toMatchArray(['qty' => 1, 'name' => 'Pizza', 'options' => 'Large'])->and($feed['orders'][0]['total'])->toBe('$18.00');
    });

    it('tells each role what it may do with an order', function () {
        [$r, $d] = odShop();
        $order = odPlace($r, $d);
        odIn($r, fn () => app(OrderService::class)->transition($order, 'accepted'));
        $allowed = fn (string $role) => $this->actingAs(odUser($r, $role))->getJson('/orders/feed')->json('orders.0.allowed');

        expect($allowed(Permissions::OWNER))->toBe(['preparing', 'cancelled'])->and($allowed(Permissions::MANAGER))->toBe(['preparing', 'cancelled'])
            ->and($allowed(Permissions::KITCHEN))->toBe(['preparing'])->and($allowed(Permissions::WAITER))->toBe([])->and($allowed(Permissions::CASHIER))->toBe(['preparing', 'cancelled']);
    });

    it('moves an order forward as the owner', function () {
        [$r, $d] = odShop();
        $order = odPlace($r, $d);
        $this->actingAs(odUser($r, Permissions::OWNER));

        $this->postJson(route('orders.status', $order->id), ['status' => 'accepted'])->assertOk()->assertJsonPath('status', 'accepted');
        $this->postJson(route('orders.status', $order->id), ['status' => 'completed'])->assertForbidden(); // not an allowed next step
        $this->postJson(route('orders.status', $order->id), ['status' => 'bogus'])->assertStatus(422);
        expect(odIn($r, fn () => $order->fresh()->status))->toBe('accepted');
    });

    it('lets the kitchen cook and finish dishes but not accept, cancel or hand over', function () {
        [$r, $d] = odShop();
        $order = odPlace($r, $d);
        $kitchen = odUser($r, Permissions::KITCHEN);
        $this->actingAs($kitchen);

        $this->postJson(route('orders.status', $order->id), ['status' => 'accepted'])->assertForbidden();
        $this->postJson(route('orders.status', $order->id), ['status' => 'cancelled'])->assertForbidden();
        $this->postJson(route('orders.status', $order->id), ['status' => 'preparing'])->assertOk();
        $this->postJson(route('orders.status', $order->id), ['status' => 'ready'])->assertOk();
        $this->postJson(route('orders.status', $order->id), ['status' => 'completed'])->assertForbidden();
        expect(odIn($r, fn () => $order->fresh()->status))->toBe('ready');
    });

    it('keeps waiters from changing orders', function () {
        [$r, $d] = odShop();
        $order = odPlace($r, $d);

        $this->actingAs(odUser($r, Permissions::WAITER))->postJson(route('orders.status', $order->id), ['status' => 'accepted'])->assertForbidden();
        $this->postJson(route('orders.pay', $order->id), ['method' => 'cash'])->assertForbidden();
        $this->postJson(route('orders.pause'))->assertForbidden();
    });

    it('cancels with a reason and keeps it in the history', function () {
        [$r, $d] = odShop();
        $order = odPlace($r, $d);
        $manager = odUser($r, Permissions::MANAGER);

        $this->actingAs($manager)->post(route('orders.status', $order->id), ['status' => 'cancelled', 'reason' => 'Out of dough'])->assertRedirect();

        $this->get(route('orders.show', $order->id))->assertOk()->assertSee('Out of dough')->assertSee($manager->name);
        expect(odIn($r, fn () => $order->fresh()))->status->toBe('cancelled')->cancel_reason->toBe('Out of dough');
    });

    it('hides other restaurants orders on every route', function () {
        [$mine, $dm] = odShop();
        [$theirs, $dt] = odShop();
        $foreign = odPlace($theirs, $dt);
        $this->actingAs(odUser($mine, Permissions::OWNER));

        $this->get(route('orders.show', $foreign->id))->assertNotFound();
        $this->get(route('orders.ticket', $foreign->id))->assertNotFound();
        $this->postJson(route('orders.status', $foreign->id), ['status' => 'accepted'])->assertNotFound();
        $this->postJson(route('orders.pay', $foreign->id), ['method' => 'cash'])->assertNotFound();
        expect(odIn($theirs, fn () => $foreign->fresh()->status))->toBe('new');
    });

    it('takes payment from the cashier and owner only once', function () {
        [$r, $d] = odShop();
        $order = odPlace($r, $d);

        $this->actingAs(odUser($r, Permissions::CASHIER))->postJson(route('orders.pay', $order->id), ['method' => 'card'])->assertOk();
        $this->postJson(route('orders.pay', $order->id), ['method' => 'cash'])->assertStatus(422)->assertJsonPath('error', 'cannot_pay');
        $this->postJson(route('orders.pay', odPlace($r, $d)->id), ['method' => 'gold'])->assertStatus(422);
        expect(odIn($r, fn () => $order->fresh()->payment_method))->toBe('card');
    });

    it('pauses and resumes online ordering', function () {
        [$r, $d] = odShop();
        $manager = odUser($r, Permissions::MANAGER);

        $this->actingAs($manager)->postJson(route('orders.pause'))->assertOk()->assertJsonPath('accepting', false);
        $this->postJson('/r/'.$r->slug.'/order', odBody($d))->assertStatus(422)->assertJsonPath('error', 'closed');
        $this->actingAs($manager)->postJson(route('orders.pause'))->assertOk()->assertJsonPath('accepting', true);
        $this->postJson('/r/'.$r->slug.'/order', odBody($d))->assertCreated();
    });

    it('prints a ticket and shows order details', function () {
        [$r, $d] = odShop();
        $order = odPlace($r, $d, ['note' => 'Ring the bell']);
        $this->actingAs(odUser($r, Permissions::WAITER));

        $this->get(route('orders.ticket', $order->id))->assertOk()->assertSee('#1001')->assertSee('Pizza')->assertSee('Large')->assertSee('Ring the bell')->assertSee('window.print()', false);
        $this->get(route('orders.show', $order->id))->assertOk()->assertSee('Pizza')->assertSee('$18.00')->assertSee(__('orders.event_placed'));
    });
});

describe('ordering settings', function () {
    it('saves the rules and uses them straight away', function () {
        [$r, $d] = odShop();
        $owner = odUser($r, Permissions::OWNER);

        $this->actingAs($owner)->put(route('orders.settings.update'), [
            'dine_in' => 1, 'takeaway' => 1, 'delivery' => 1, 'tax_rate' => '8.5', 'service_rate' => '5', 'delivery_fee' => '2', 'delivery_min' => '10',
            'pay_cash' => 1, 'auto_accept' => 1, 'prep_minutes' => 25, 'enabled' => 1,
        ])->assertRedirect();

        $s = app(OrderSettings::class)->for($r->fresh());
        expect($s)->toMatchArray(['delivery' => true, 'tax_rate' => '8.5', 'service_rate' => '5', 'auto_accept' => true, 'prep_minutes' => 25, 'pay_card' => false, 'allow_cancel' => false])
            ->and(app(OrderSettings::class)->types($r->fresh()))->toBe(['dine_in', 'takeaway', 'delivery'])->and(app(OrderSettings::class)->paymentMethods($r->fresh()))->toBe(['cash']);
    });

    it('needs one order type and one payment method', function () {
        [$r] = odShop();
        $this->actingAs(odUser($r, Permissions::OWNER));

        $this->put(route('orders.settings.update'), ['pay_cash' => 1])->assertSessionHasErrors('dine_in');
        $this->put(route('orders.settings.update'), ['dine_in' => 1])->assertSessionHasErrors('pay_cash');
        $this->put(route('orders.settings.update'), ['dine_in' => 1, 'pay_cash' => 1, 'tax_rate' => '150', 'prep_minutes' => 0])->assertSessionHasErrors(['tax_rate', 'prep_minutes']);
    });

    it('is for people who manage settings', function () {
        [$r] = odShop();

        $this->actingAs(odUser($r, Permissions::MANAGER))->get(route('orders.settings'))->assertForbidden();
        $this->actingAs(odUser($r, Permissions::OWNER))->get(route('orders.settings'))->assertOk()->assertSee(__('orders.settings_title'));
    });

    it('reads defaults for restaurants that never saved settings', function () {
        $r = Restaurant::create(['name' => 'Fresh', 'slug' => 'fresh'.uniqid()]);
        $settings = app(OrderSettings::class);

        expect($settings->for($r))->toMatchArray(['enabled' => true, 'dine_in' => true, 'takeaway' => true, 'delivery' => false])
            ->and($settings->accepting($r))->toBeTrue()->and($settings->types($r))->toBe(['dine_in', 'takeaway'])->and($settings->cents($r, 'delivery_fee'))->toBe(0);
    });
});
