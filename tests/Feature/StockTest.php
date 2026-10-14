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
use App\Modules\Orders\Exceptions\OrderException;
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

function stShop(): array
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

function stOrder(Restaurant $r, int $productId, int $qty): Order
{
    return app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => [['product_id' => $productId, 'qty' => $qty]]]));
}

function stStock(Restaurant $r, Product $p): ?int
{
    return app(TenantContext::class)->runAs($r, fn () => Product::find($p->id)->stock_qty);
}

it('takes portions off the stock with every order and sells out at zero', function () {
    [$r, , $d] = stShop();
    stOrder($r, $d['cake']->id, 2);
    expect(stStock($r, $d['cake']))->toBe(1);
    stOrder($r, $d['cake']->id, 1);
    expect(stStock($r, $d['cake']))->toBe(0);

    expect(fn () => stOrder($r, $d['cake']->id, 1))->toThrow(OrderException::class);
    $this->postJson("/r/{$r->slug}/cart/quote", ['lines' => [['product_id' => $d['cake']->id, 'qty' => 1]]])->assertJsonPath('lines.0.errors.0', 'sold_out');
});

it('refuses more than is left and leaves the stock alone', function () {
    [$r, , $d] = stShop();
    $this->postJson("/r/{$r->slug}/cart/quote", ['lines' => [['product_id' => $d['cake']->id, 'qty' => 4]]])->assertJsonPath('lines.0.errors.0', 'stock_limit');
    expect(fn () => stOrder($r, $d['cake']->id, 4))->toThrow(OrderException::class);
    expect(stStock($r, $d['cake']))->toBe(3)->and(app(TenantContext::class)->runAs($r, fn () => Order::count()))->toBe(0);
});

it('does not touch untracked dishes', function () {
    [$r, , $d] = stShop();
    stOrder($r, $d['soup']->id, 5);
    expect(stStock($r, $d['soup']))->toBeNull();
});

it('gives portions back when an order is cancelled', function () {
    [$r, , $d] = stShop();
    $o = stOrder($r, $d['cake']->id, 2);
    app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->transition(Order::find($o->id), OrderStatus::CANCELLED));
    expect(stStock($r, $d['cake']))->toBe(3);
});

it('shows the new stock on the guest menu straight away', function () {
    [$r, , $d] = stShop();
    $this->get("/r/{$r->slug}")->assertOk();
    stOrder($r, $d['cake']->id, 3);
    $this->get("/r/{$r->slug}")->assertSee(__('customer.sold_out'));
});

it('sets stock from the product form and flags low stock in the list', function () {
    [$r, $owner, $d] = stShop();
    $this->actingAs($owner)->put(route('menu.products.update', $d['soup']->id), ['category_id' => $d['cat']->id, 'name' => ['en' => 'Soup'], 'price' => 4, 'stock_qty' => 2, 'low_stock_at' => 5, 'is_active' => '1', 'is_available' => '1'])->assertSessionHasNoErrors();
    expect(stStock($r, $d['soup']))->toBe(2);

    $this->actingAs($owner)->get(route('menu.index'))->assertOk()->assertSee('Only 2 left');
    $this->actingAs($owner)->put(route('menu.products.update', $d['soup']->id), ['category_id' => $d['cat']->id, 'name' => ['en' => 'Soup'], 'price' => 4, 'is_active' => '1', 'is_available' => '1']);
    expect(stStock($r, $d['soup']))->toBeNull();
});

it('edits prices and stock for the whole menu in one go', function () {
    [$r, $owner, $d] = stShop();
    $this->actingAs($owner)->get(route('menu.stock.index'))->assertOk()->assertSee('Cake')->assertSee('Soup');
    $this->actingAs($owner)->put(route('menu.stock.update'), ['rows' => [
        $d['cake']->id => ['price' => '6.50', 'stock_qty' => '10', 'low_stock_at' => '3', 'is_available' => '1'],
        $d['soup']->id => ['price' => '4.00', 'stock_qty' => '', 'low_stock_at' => ''],
    ]])->assertSessionHasNoErrors();

    $cake = app(TenantContext::class)->runAs($r, fn () => Product::find($d['cake']->id));
    $soup = app(TenantContext::class)->runAs($r, fn () => Product::find($d['soup']->id));
    expect((float) $cake->price)->toBe(6.5)->and($cake->stock_qty)->toBe(10)->and($cake->low_stock_at)->toBe(3)->and($soup->stock_qty)->toBeNull()->and($soup->is_available)->toBeFalse();
});

it('ignores other restaurants’ products and rejects bad numbers', function () {
    [$r, $owner, $d] = stShop();
    [, , $other] = stShop();
    $this->actingAs($owner)->put(route('menu.stock.update'), ['rows' => [$other['cake']->id => ['price' => '0.01', 'stock_qty' => '0']]])->assertSessionHasNoErrors();
    expect(app(TenantContext::class)->runAs($other['cake']->restaurant, fn () => (float) Product::find($other['cake']->id)->price))->toBe(5.0);

    $this->actingAs($owner)->put(route('menu.stock.update'), ['rows' => [$d['cake']->id => ['price' => '-1']]])->assertSessionHasErrors('rows.'.$d['cake']->id.'.price');
});

it('changes prices by a percentage for one category or everything', function () {
    [$r, $owner, $d] = stShop();
    $this->actingAs($owner)->post(route('menu.stock.adjust'), ['percent' => '10'])->assertSessionHas('status');
    $price = fn ($p) => app(TenantContext::class)->runAs($r, fn () => (float) Product::find($p->id)->price);
    expect($price($d['cake']))->toBe(5.5)->and($price($d['soup']))->toBe(4.4);

    $this->actingAs($owner)->post(route('menu.stock.adjust'), ['percent' => '-50', 'category_id' => $d['cat']->id]);
    expect($price($d['cake']))->toBe(2.75);
    $this->actingAs($owner)->post(route('menu.stock.adjust'), ['percent' => '0'])->assertSessionHasErrors('percent');
    $this->actingAs($owner)->post(route('menu.stock.adjust'), ['percent' => '-95'])->assertSessionHasErrors('percent');
});

it('exports the menu as a safe CSV and keeps waiters out of these screens', function () {
    [$r, $owner, $d] = stShop();
    app(TenantContext::class)->runAs($r, fn () => $d['soup']->update(['name' => ['en' => '=BAD()']]));
    $csv = $this->actingAs($owner)->get(route('menu.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('Cake')->toContain("'=BAD()");

    $waiter = User::factory()->create(['restaurant_id' => $r->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
    $waiter->assignRole('waiter');
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $this->actingAs($waiter)->get(route('menu.stock.index'))->assertForbidden();
    $this->actingAs($waiter)->post(route('menu.stock.adjust'), ['percent' => '10'])->assertForbidden();
});
