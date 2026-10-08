<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Branches\Models\Branch;
use App\Modules\Branches\Models\BranchProduct;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    Cache::flush();
});

function brShop(array $limits = []): array
{
    $r = Restaurant::create(['name' => 'Branch Bistro', 'slug' => 'br'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now()]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => $limits, 'features' => []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $owner = brUser($r, Permissions::OWNER);
    $d = app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Food'], 'sort' => 1]);

        return [
            'pizza' => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Pizza'], 'price' => 10, 'sort' => 1]),
            'north' => Branch::create(['name' => 'North', 'slug' => 'north', 'sort' => 1]),
            'south' => Branch::create(['name' => 'South', 'slug' => 'south', 'sort' => 2]),
        ];
    });

    return [$r, $owner, $d];
}

function brUser(Restaurant $r, string $role, array $attrs = []): User
{
    $user = User::factory()->create(['restaurant_id' => $r->id] + $attrs);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($r->id);
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return $user;
}

function brOrder(Restaurant $r, int $productId, array $extra = [], int $qty = 1): Order
{
    return app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, $extra + ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => [['product_id' => $productId, 'qty' => $qty]]]));
}

it('lets the owner add, edit and delete branches within the plan limit', function () {
    [$r, $owner] = brShop(['branches' => 3]);
    $this->actingAs($owner)->post(route('branches.store'), ['name' => 'Harbour', 'is_active' => 1, 'schedule' => ['days' => [1, 2], 'from' => '09:00', 'to' => '18:00']])->assertRedirect(route('branches.index'));
    $branch = app(TenantContext::class)->runAs($r, fn () => Branch::where('slug', 'harbour')->first());
    expect($branch->schedule['from'])->toBe('09:00');

    $this->actingAs($owner)->get(route('branches.index'))->assertOk()->assertSee('Harbour');
    $this->actingAs($owner)->put(route('branches.update', $branch->id), ['name' => 'Harbour 2'])->assertRedirect();
    expect(app(TenantContext::class)->runAs($r, fn () => Branch::find($branch->id)->name))->toBe('Harbour 2');

    // The plan allows three: North, South and Harbour. A fourth is refused.
    $this->actingAs($owner)->post(route('branches.store'), ['name' => 'Fourth'])->assertSessionHasErrors('limit');
    $this->actingAs($owner)->delete(route('branches.destroy', $branch->id))->assertRedirect(route('branches.index'));
    expect(app(TenantContext::class)->runAs($r, fn () => Branch::count()))->toBe(2);
});

it('keeps branches away from staff without the permission and from other restaurants', function () {
    [$r, , $d] = brShop();
    [, $otherOwner] = brShop();
    $this->actingAs(brUser($r, Permissions::WAITER))->get(route('branches.index'))->assertForbidden();
    $this->actingAs($otherOwner)->get(route('branches.edit', $d['north']->id))->assertNotFound();
});

it('stamps the order with the branch of the table and refuses an order with no clear branch', function () {
    [$r, , $d] = brShop();
    $table = app(TenantContext::class)->runAs($r, fn () => DiningTable::create(['name' => 'T1', 'branch_id' => $d['south']->id]));

    $order = brOrder($r, $d['pizza']->id, ['type' => 'dine_in', 'table_id' => $table->id]);
    expect($order->branch_id)->toBe($d['south']->id);

    expect(fn () => brOrder($r, $d['pizza']->id))->toThrow(OrderException::class);
    expect(brOrder($r, $d['pizza']->id, ['branch_id' => $d['north']->id])->branch_id)->toBe($d['north']->id);
});

it('refuses guest orders in a closed branch but lets staff through', function () {
    [$r, , $d] = brShop();
    app(TenantContext::class)->runAs($r, fn () => $d['north']->update(['is_active' => true, 'schedule' => ['days' => [], 'from' => '03:00', 'to' => '03:01']]));
    $now = CarbonImmutable::parse('2026-03-02 12:00:00', 'UTC');
    CarbonImmutable::setTestNow($now);

    expect(fn () => brOrder($r, $d['pizza']->id, ['branch_id' => $d['north']->id]))->toThrow(OrderException::class, 'branch_closed');

    $staff = app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, ['type' => 'takeaway', 'payment_method' => 'cash', 'branch_id' => $d['north']->id, 'lines' => [['product_id' => $d['pizza']->id, 'qty' => 1]]], 'staff'));
    expect($staff->branch_id)->toBe($d['north']->id);
    CarbonImmutable::setTestNow();
});

it('applies a branch price, sold-out switch and its own stock', function () {
    [$r, , $d] = brShop();
    app(TenantContext::class)->runAs($r, fn () => BranchProduct::create(['branch_id' => $d['south']->id, 'product_id' => $d['pizza']->id, 'price' => 12.5, 'stock_qty' => 2]));

    $north = brOrder($r, $d['pizza']->id, ['branch_id' => $d['north']->id]);
    $south = brOrder($r, $d['pizza']->id, ['branch_id' => $d['south']->id]);
    expect($north->subtotal_cents)->toBe(1000)->and($south->subtotal_cents)->toBe(1250);

    // South had two portions: one is gone, then the second, then it sells out. North keeps selling.
    brOrder($r, $d['pizza']->id, ['branch_id' => $d['south']->id]);
    expect(fn () => brOrder($r, $d['pizza']->id, ['branch_id' => $d['south']->id]))->toThrow(OrderException::class);
    expect(brOrder($r, $d['pizza']->id, ['branch_id' => $d['north']->id])->id)->toBeInt();

    app(TenantContext::class)->runAs($r, fn () => BranchProduct::create(['branch_id' => $d['north']->id, 'product_id' => $d['pizza']->id, 'is_available' => false]));
    expect(fn () => brOrder($r, $d['pizza']->id, ['branch_id' => $d['north']->id]))->toThrow(OrderException::class);
});

it('edits overrides on the prices screen and copies them to another branch', function () {
    [$r, $owner, $d] = brShop();
    $this->actingAs($owner)->get(route('branches.menu', $d['north']->id))->assertOk()->assertSee('Pizza');
    $this->actingAs($owner)->put(route('branches.menu.update', $d['north']->id), ['items' => [$d['pizza']->id => ['price' => '11.25', 'available' => '', 'stock_qty' => '']]])->assertRedirect();
    $row = app(TenantContext::class)->runAs($r, fn () => BranchProduct::where('branch_id', $d['north']->id)->first());
    expect((float) $row->price)->toBe(11.25)->and($row->is_available)->toBeNull();

    $this->actingAs($owner)->post(route('branches.menu.copy', $d['south']->id), ['from' => $d['north']->id])->assertRedirect();
    expect((float) app(TenantContext::class)->runAs($r, fn () => BranchProduct::where('branch_id', $d['south']->id)->first())->price)->toBe(11.25);

    // Blank everything and the override disappears.
    $this->actingAs($owner)->put(route('branches.menu.update', $d['north']->id), ['items' => [$d['pizza']->id => ['price' => '', 'available' => '', 'stock_qty' => '']]]);
    expect(app(TenantContext::class)->runAs($r, fn () => BranchProduct::where('branch_id', $d['north']->id)->count()))->toBe(0);
});

it('asks the guest to choose a branch, then shows that branch menu and prices', function () {
    [$r, , $d] = brShop();
    app(TenantContext::class)->runAs($r, fn () => BranchProduct::create(['branch_id' => $d['south']->id, 'product_id' => $d['pizza']->id, 'price' => 99]));

    $this->get('/r/'.$r->slug)->assertOk()->assertSee(__('branches.choose_title'))->assertSee('North')->assertDontSee('Pizza');
    $html = $this->get('/r/'.$r->slug.'?branch=south')->assertOk()->assertSee('Pizza')->getContent();
    expect($html)->toContain('99');

    // The choice sticks for the rest of the visit, and the quote uses that branch's price.
    $this->get('/r/'.$r->slug)->assertOk()->assertSee('Pizza');
    $this->postJson('/r/'.$r->slug.'/cart/quote', ['lines' => [['product_id' => $d['pizza']->id, 'qty' => 1]]])->assertOk()->assertJsonPath('subtotal_cents', 9900);
});

it('goes straight to the menu when there is only one branch', function () {
    [$r, , $d] = brShop();
    app(TenantContext::class)->runAs($r, fn () => $d['south']->delete());
    $this->get('/r/'.$r->slug)->assertOk()->assertSee('Pizza')->assertSee('North');
});

it('lets the guest order from the chosen branch through the storefront', function () {
    [$r, , $d] = brShop();
    $this->get('/r/'.$r->slug.'?branch=north');
    $this->postJson('/r/'.$r->slug.'/order', ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => [['product_id' => $d['pizza']->id, 'qty' => 1]]])->assertCreated();
    expect(app(TenantContext::class)->runAs($r, fn () => Order::first()->branch_id))->toBe($d['north']->id);
});

it('fixes branch staff to their branch and filters the order board', function () {
    [$r, $owner, $d] = brShop();
    brOrder($r, $d['pizza']->id, ['branch_id' => $d['north']->id]);
    brOrder($r, $d['pizza']->id, ['branch_id' => $d['south']->id]);
    $waiter = brUser($r, Permissions::WAITER, ['branch_id' => $d['south']->id]);

    $feed = fn ($u) => $this->actingAs($u)->getJson(route('orders.feed'))->json('orders');
    expect($feed($owner))->toHaveCount(2)->and($feed($waiter))->toHaveCount(1);

    // Owners can switch; a fixed waiter cannot.
    $this->actingAs($owner)->post(route('branches.switch'), ['branch' => $d['north']->id])->assertRedirect();
    expect($feed($owner))->toHaveCount(1);
    $this->actingAs($owner)->post(route('branches.switch'), ['branch' => 'all']);
    expect($feed($owner))->toHaveCount(2);
    $this->actingAs($waiter)->post(route('branches.switch'), ['branch' => $d['north']->id])->assertForbidden();
});

it('stamps till orders with the branch the staff member works in', function () {
    [$r, $owner, $d] = brShop();
    $payload = ['type' => 'takeaway', 'lines' => [['product_id' => $d['pizza']->id, 'qty' => 1]]];
    $this->actingAs($owner)->postJson(route('orders.pos.store'), $payload)->assertStatus(422)->assertJsonPath('error', 'branch_required');

    $this->actingAs($owner)->post(route('branches.switch'), ['branch' => $d['south']->id]);
    $this->actingAs($owner)->postJson(route('orders.pos.store'), $payload)->assertCreated();
    expect(app(TenantContext::class)->runAs($r, fn () => Order::first()->branch_id))->toBe($d['south']->id);
});

it('assigns a table and a staff member to a branch from their forms', function () {
    [$r, $owner, $d] = brShop();
    $this->actingAs($owner)->post(route('tables.store'), ['name' => 'Patio', 'branch_id' => $d['north']->id])->assertRedirect();
    $table = app(TenantContext::class)->runAs($r, fn () => DiningTable::where('name', 'Patio')->first());
    expect($table->branch_id)->toBe($d['north']->id);

    $this->actingAs($owner)->get(route('team.create'))->assertOk()->assertSee(__('branches.staff_branch'));
    $waiter = brUser($r, Permissions::WAITER);
    $this->actingAs($owner)->put(route('team.update', $waiter->id), ['role' => 'waiter', 'branch_id' => $d['south']->id])->assertRedirect();
    expect($waiter->fresh()->branch_id)->toBe($d['south']->id);

    // A branch of another restaurant is not accepted.
    [, , $other] = brShop();
    $this->actingAs($owner)->put(route('team.update', $waiter->id), ['role' => 'waiter', 'branch_id' => $other['north']->id])->assertSessionHasErrors('branch_id');
});
