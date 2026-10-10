<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Inventory\Models\Ingredient;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
});

function ivShop(): array
{
    $r = Restaurant::create(['name' => 'Stock Bistro', 'slug' => 'sb'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'onboarded_at' => now()]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $owner = User::factory()->create(['restaurant_id' => $r->id]);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($r->id);
    $owner->assignRole(Permissions::OWNER);
    $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $d = app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Food'], 'sort' => 1]);

        return ['cat' => $cat, 'cake' => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Cake'], 'price' => 5, 'sort' => 1, 'stock_qty' => 3, 'low_stock_at' => 2]), 'soup' => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Soup'], 'price' => 4, 'sort' => 2])];
    });

    return [$r, $owner, $d];
}

function ivOrder(Restaurant $r, int $productId, int $qty): Order
{
    return app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => [['product_id' => $productId, 'qty' => $qty]]]));
}

it('records deliveries, averages the unit cost and prices a recipe', function () {
    [$r, $owner, $d] = ivShop();
    $this->actingAs($owner)->post(route('inventory.store'), ['name' => 'Flour', 'unit' => 'kg', 'stock_qty' => 10, 'unit_cost' => 2, 'low_at' => 3])->assertRedirect();
    $flour = app(TenantContext::class)->runAs($r, fn () => Ingredient::first());

    // 10 kg at 2 + 10 kg at 4 = 20 kg at an average of 3.
    $this->actingAs($owner)->post(route('inventory.purchase'), ['ingredient_id' => $flour->id, 'qty' => 10, 'unit_cost' => 4])->assertRedirect();
    $this->actingAs($owner)->put(route('inventory.recipe.update', $d['cake']->id), ['qty' => [$flour->id => '0.2']])->assertRedirect();

    $flour = Ingredient::allTenants()->find($flour->id);
    expect($flour->stock_qty)->toBe(20.0)->and($flour->unit_cost)->toBe(3.0);
    expect((float) Product::allTenants()->find($d['cake']->id)->cost_price)->toBe(0.6);
    $this->actingAs($owner)->get(route('inventory.index'))->assertOk()->assertSee('Flour');
    $this->actingAs($owner)->get(route('inventory.recipes'))->assertOk();
    $this->actingAs($owner)->get(route('inventory.recipe', $d['cake']->id))->assertOk();
});

it('uses up ingredients with an order and gives them back on cancel, without ever blocking the order', function () {
    [$r, $owner, $d] = ivShop();
    $flour = app(TenantContext::class)->runAs($r, fn () => Ingredient::create(['name' => 'Flour', 'unit' => 'kg', 'stock_qty' => 0.3, 'unit_cost' => 2]));
    $this->actingAs($owner)->put(route('inventory.recipe.update', $d['cake']->id), ['qty' => [$flour->id => '0.2']]);

    $o = ivOrder($r, $d['cake']->id, 2); // needs 0.4, only 0.3 on hand: the order still goes through
    expect(Ingredient::allTenants()->find($flour->id)->stock_qty)->toEqualWithDelta(-0.1, 0.0001);
    app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->transition(Order::find($o->id), OrderStatus::CANCELLED));
    expect(Ingredient::allTenants()->find($flour->id)->stock_qty)->toEqualWithDelta(0.3, 0.0001);
});

it('keeps other restaurants’ ingredients out of reach and rejects bad input', function () {
    [$r1, $owner1, $d1] = ivShop();
    [$r2] = ivShop();
    $theirs = app(TenantContext::class)->runAs($r2, fn () => Ingredient::create(['name' => 'Secret', 'unit' => 'kg', 'stock_qty' => 5]));

    $this->actingAs($owner1)->put(route('inventory.update', $theirs->id), ['name' => 'Hacked', 'unit' => 'kg'])->assertNotFound();
    $this->actingAs($owner1)->post(route('inventory.purchase'), ['ingredient_id' => $theirs->id, 'qty' => 1, 'unit_cost' => 1])->assertNotFound();
    $this->actingAs($owner1)->post(route('inventory.store'), ['name' => 'X', 'unit' => 'bushel'])->assertSessionHasErrors('unit');
    expect(Ingredient::allTenants()->find($theirs->id)->name)->toBe('Secret');
});
