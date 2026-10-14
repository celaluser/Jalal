<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Models\OrderPayment;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\TipPool;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
});

function tipUser(Restaurant $r, string $role, string $name): User
{
    $u = User::factory()->create(['restaurant_id' => $r->id, 'name' => $name]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $u->assignRole($role);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return $u;
}

function tipShop(int $tipCents = 1000): array
{
    $r = Restaurant::create(['name' => 'Tip Bistro', 'slug' => 'tp'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now()]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $owner = tipUser($r, Permissions::OWNER, 'Olga');
    $waiter = tipUser($r, Permissions::WAITER, 'Wes');
    $cook = tipUser($r, Permissions::KITCHEN, 'Kim');

    if ($tipCents > 0) {
        app(TenantContext::class)->runAs($r, function () use ($r, $tipCents) {
            $c = Category::create(['name' => 'M', 'is_active' => true]);
            $p = Product::create(['category_id' => $c->id, 'name' => 'Soup', 'price' => 10, 'is_active' => true]);
            $order = app(OrderService::class)->place($r, ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => [['product_id' => $p->id, 'qty' => 1]]], 'staff');
            OrderPayment::create(['order_id' => $order->id, 'method' => 'cash', 'amount_cents' => 1000, 'tip_cents' => $tipCents, 'status' => OrderPayment::PAID, 'paid_at' => now()]);
        });
    }

    return [$r, $owner, $waiter, $cook];
}

it('splits cents by weight so the parts always add up', function () {
    $pool = app(TipPool::class);

    $a = $pool->split(1000, 0, [1 => 1, 2 => 1, 3 => 1]);
    expect(array_sum($a['shares']))->toBe(1000)->and(max($a['shares']) - min($a['shares']))->toBeLessThanOrEqual(1);

    $b = $pool->split(1000, 10, [1 => 2, 2 => 1, 3 => 0]);
    expect($b['house'])->toBe(100)->and($b['pool'])->toBe(900)->and($b['shares'])->toBe([1 => 600, 2 => 300]);

    expect($pool->split(500, 0, [1 => 0]))->toMatchArray(['house' => 500, 'pool' => 0, 'shares' => []]);
});

it('shows the collected tips and each persons share, and saves weights and the house cut', function () {
    [$r, $owner, $waiter, $cook] = tipShop(1000);

    $this->actingAs($owner)->get(route('tips.index'))->assertOk()->assertSee('Wes')->assertSee('Kim')->assertSee('$10.00');

    $this->actingAs($owner)->put(route('tips.update'), ['weights' => [$owner->id => 0, $waiter->id => 2, $cook->id => 1], 'house_percent' => 10])->assertRedirect();

    $pool = app(TipPool::class);
    expect($pool->housePercent($r))->toBe(10.0)->and($pool->weights($r))->toEqual([$owner->id => 0.0, $waiter->id => 2.0, $cook->id => 1.0]);
    $this->actingAs($owner)->get(route('tips.index'))->assertOk()->assertSee('$6.00')->assertSee('$3.00');

    $csv = $this->actingAs($owner)->get(route('tips.export'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->getContent();
    expect($csv)->toContain('Wes,2,6.00')->toContain('Kim,1,3.00')->toContain('10.00');
});

it('ignores forged staff ids and only lets managers of payments change the weights', function () {
    [$r, $owner, $waiter] = tipShop(0);
    $stranger = tipUser(Restaurant::create(['name' => 'Other', 'slug' => 'ot'.uniqid(), 'locale' => 'en', 'currency_code' => 'USD', 'timezone' => 'UTC']), Permissions::OWNER, 'Eve');

    $this->actingAs($owner)->put(route('tips.update'), ['weights' => [$stranger->id => 5, $waiter->id => 3]]);
    expect(app(TipPool::class)->weights($r))->not->toHaveKey($stranger->id)->and(app(TipPool::class)->weights($r)[$waiter->id])->toBe(3.0);

    $this->actingAs($waiter)->get(route('tips.index'))->assertForbidden();
    $this->actingAs($waiter)->put(route('tips.update'), ['weights' => [$waiter->id => 99]])->assertForbidden();
});
