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
use App\Modules\Orders\Models\Order;
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

/** @return array{0: Restaurant, 1: array<string, mixed>} */
function psShop(array $settings = []): array
{
    $r = Restaurant::create(['name' => 'Pos Place', 'slug' => 'pos'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'onboarded_at' => now(), 'order_settings' => $settings]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());

    $d = app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Food'], 'sort' => 1]);
        $pizza = Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Pizza'], 'price' => 10, 'sort' => 1]);
        $soda = Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Soda'], 'price' => 2.5, 'sort' => 2]);
        $size = OptionGroup::create(['name' => ['en' => 'Size'], 'type' => 'single', 'is_required' => true]);
        $size->options()->create(['name' => ['en' => 'Regular'], 'price_delta' => 0, 'sort' => 1]);
        $large = $size->options()->create(['name' => ['en' => 'Large'], 'price_delta' => 3, 'sort' => 2]);
        $pizza->optionGroups()->attach($size->id, ['sort' => 0]);

        return ['pizza' => $pizza, 'soda' => $soda, 'large' => $large, 'table' => DiningTable::create(['name' => '4'])];
    });

    return [$r, $d];
}

function psUser(Restaurant $r, string $role): User
{
    $user = User::factory()->create(['restaurant_id' => $r->id]);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($r->id);
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return $user;
}

function psPayload(array $d, array $over = []): array
{
    return array_merge([
        'type' => 'dine_in', 'table_id' => $d['table']->id,
        'lines' => [['product_id' => $d['pizza']->id, 'qty' => 2, 'options' => [$d['large']->id]], ['product_id' => $d['soda']->id, 'qty' => 1]],
    ], $over);
}

it('lets a waiter open the screen with the menu and tables', function () {
    [$r] = psShop();
    $this->actingAs(psUser($r, 'waiter'))->get(route('orders.pos.index'))->assertOk()->assertSee('Pizza')->assertSee('Table 4');
});

it('keeps kitchen staff and guests out', function () {
    [$r, $d] = psShop();
    $kitchen = psUser($r, 'kitchen');
    $this->actingAs($kitchen)->get(route('orders.pos.index'))->assertForbidden();
    $this->actingAs($kitchen)->postJson(route('orders.pos.store'), psPayload($d))->assertForbidden();
    auth()->logout();
    $this->get(route('orders.pos.index'))->assertRedirect(route('login'));
});

it('lets managers and cashiers take orders too', function () {
    [$r] = psShop();
    foreach (['manager', 'cashier', Permissions::OWNER] as $role) {
        $this->actingAs(psUser($r, $role))->get(route('orders.pos.index'))->assertOk();
    }
});

it('places a re-priced, already accepted staff order for a table', function () {
    Event::fake([OrderPlaced::class]);
    [$r, $d] = psShop();
    $waiter = psUser($r, 'waiter');

    $res = $this->actingAs($waiter)->postJson(route('orders.pos.store'), psPayload($d))->assertCreated();

    $order = app(TenantContext::class)->runAs($r, fn () => Order::with('items')->first());
    expect($res->json('number'))->toBe($order->number)
        ->and($order->source)->toBe('staff')->and($order->status)->toBe('accepted')
        ->and($order->subtotal_cents)->toBe(2 * 1300 + 250)->and($order->table_name)->toBe('4')
        ->and($order->items)->toHaveCount(2);
    Event::assertDispatched(OrderPlaced::class);
});

it('ignores prices sent by the browser', function () {
    [$r, $d] = psShop();
    $payload = psPayload($d);
    $payload['lines'][1]['unit_cents'] = 1;
    $payload['lines'][1]['price'] = 0.01;
    $this->actingAs(psUser($r, 'waiter'))->postJson(route('orders.pos.store'), $payload)->assertCreated();

    expect(app(TenantContext::class)->runAs($r, fn () => Order::first()->subtotal_cents))->toBe(2850);
});

it('is not stopped by paused online ordering or missing guest details', function () {
    [$r, $d] = psShop(['enabled' => false]);
    $res = $this->actingAs(psUser($r, 'waiter'))->postJson(route('orders.pos.store'), psPayload($d, ['type' => 'takeaway', 'table_id' => null]));

    $res->assertCreated();
});

it('still needs a table for dine-in and an address for delivery', function () {
    [$r, $d] = psShop();
    $waiter = psUser($r, 'waiter');
    $this->actingAs($waiter)->postJson(route('orders.pos.store'), psPayload($d, ['table_id' => null]))->assertStatus(422)->assertJsonPath('error', 'table_required');
    $this->actingAs($waiter)->postJson(route('orders.pos.store'), psPayload($d, ['type' => 'delivery', 'table_id' => null]))->assertStatus(422)->assertJsonPath('error', 'address_required');
});

it('rejects a table or dish of another restaurant and invalid carts', function () {
    [$r, $d] = psShop();
    [, $other] = psShop();
    $waiter = psUser($r, 'waiter');

    $this->actingAs($waiter)->postJson(route('orders.pos.store'), psPayload($d, ['table_id' => $other['table']->id]))->assertStatus(422)->assertJsonPath('error', 'table_required');
    $this->actingAs($waiter)->postJson(route('orders.pos.store'), psPayload($d, ['lines' => [['product_id' => $other['soda']->id, 'qty' => 1]]]))->assertStatus(422)->assertJsonPath('error', 'cart_invalid');
    $this->actingAs($waiter)->postJson(route('orders.pos.store'), psPayload($d, ['lines' => []]))->assertStatus(422);
    $this->actingAs($waiter)->postJson(route('orders.pos.store'), psPayload($d, ['lines' => [['product_id' => $d['pizza']->id, 'qty' => 1]]]))->assertStatus(422)->assertJsonPath('error', 'cart_invalid'); // required size missing
    expect(app(TenantContext::class)->runAs($r, fn () => Order::count()))->toBe(0);
});

it('does not double-place a retried order', function () {
    [$r, $d] = psShop();
    $waiter = psUser($r, 'waiter');
    $payload = psPayload($d, ['idempotency_key' => 'abc-123']);
    $this->actingAs($waiter)->postJson(route('orders.pos.store'), $payload)->assertCreated();
    $this->actingAs($waiter)->postJson(route('orders.pos.store'), $payload)->assertCreated();

    expect(app(TenantContext::class)->runAs($r, fn () => Order::count()))->toBe(1);
});

it('marks the order paid only when the person may take payment', function () {
    [$r, $d] = psShop();
    $this->actingAs(psUser($r, 'waiter'))->postJson(route('orders.pos.store'), psPayload($d, ['paid' => true]))->assertCreated();
    $this->actingAs(psUser($r, 'cashier'))->postJson(route('orders.pos.store'), psPayload($d, ['paid' => true, 'payment_method' => 'card']))->assertCreated();

    $orders = app(TenantContext::class)->runAs($r, fn () => Order::orderBy('id')->get());
    expect($orders[0]->isPaid())->toBeFalse()->and($orders[1]->isPaid())->toBeTrue()->and($orders[1]->payment_method)->toBe('card');
});
