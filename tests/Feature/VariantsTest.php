<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\OptionGroup;
use App\Modules\Menu\Models\Product;
use App\Modules\Menu\Models\ProductVariant;
use App\Modules\Menu\Services\MenuService;
use App\Modules\Orders\Models\Order;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Cache::flush();
});

function vaShop(): array
{
    $r = Restaurant::create(['name' => 'Sizes', 'slug' => 'sz'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'onboarded_at' => now()]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => [], 'features' => []]), now()->addMonth());
    $owner = User::factory()->create(['restaurant_id' => $r->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
    $owner->assignRole(Permissions::OWNER);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $d = app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Pizza'], 'sort' => 1]);
        $pizza = Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Margherita'], 'price' => 99, 'sort' => 1]);
        $s = $pizza->variants()->create(['name' => ['en' => 'Small'], 'price' => 8, 'sort' => 0]);
        $l = $pizza->variants()->create(['name' => ['en' => 'Large'], 'price' => 14, 'sort' => 1]);

        return ['cat' => $cat, 'pizza' => $pizza, 's' => $s, 'l' => $l];
    });

    return [$r, $owner, $d];
}

function vaQuote($test, Restaurant $r, array $line): array
{
    return $test->postJson("/r/{$r->slug}/cart/quote", ['lines' => [$line]])->json('lines.0');
}

it('prices a dish by the size the guest picks, ignoring the base price', function () {
    [$r, , $d] = vaShop();
    $l = vaQuote($this, $r, ['product_id' => $d['pizza']->id, 'variant_id' => $d['l']->id, 'qty' => 2]);
    expect($l['unit_cents'])->toBe(1400)->and($l['total_cents'])->toBe(2800)->and($l['name'])->toBe('Margherita · Large')->and($l['errors'])->toBe([]);
});

it('needs a size, and rejects a size of another dish or an unavailable one', function () {
    [$r, , $d] = vaShop();
    expect(vaQuote($this, $r, ['product_id' => $d['pizza']->id, 'qty' => 1])['errors'])->toBe(['variant_required']);
    $other = app(TenantContext::class)->runAs($r, fn () => Product::create(['category_id' => $d['cat']->id, 'name' => ['en' => 'Other'], 'price' => 5])->variants()->create(['name' => ['en' => 'X'], 'price' => 1]));
    expect(vaQuote($this, $r, ['product_id' => $d['pizza']->id, 'variant_id' => $other->id, 'qty' => 1])['errors'])->toBe(['variant_required']);

    app(TenantContext::class)->runAs($r, fn () => $d['s']->update(['is_available' => false]));
    expect(vaQuote($this, $r, ['product_id' => $d['pizza']->id, 'variant_id' => $d['s']->id, 'qty' => 1])['errors'])->toContain('sold_out');
});

it('adds options on top of the size price', function () {
    [$r, , $d] = vaShop();
    $opt = app(TenantContext::class)->runAs($r, function () use ($d) {
        $g = OptionGroup::create(['name' => ['en' => 'Extras'], 'type' => 'multiple']);
        $o = $g->options()->create(['name' => ['en' => 'Cheese'], 'price_delta' => 1.5, 'sort' => 1]);
        $d['pizza']->optionGroups()->attach($g->id, ['sort' => 0]);

        return $o;
    });
    expect(vaQuote($this, $r, ['product_id' => $d['pizza']->id, 'variant_id' => $d['s']->id, 'options' => [$opt->id], 'qty' => 1])['unit_cents'])->toBe(950);
});

it('stores the size in the order and charges the size price', function () {
    [$r, , $d] = vaShop();
    $this->postJson("/r/{$r->slug}/order", ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => [['product_id' => $d['pizza']->id, 'variant_id' => $d['l']->id, 'qty' => 1]]])->assertCreated();
    $order = app(TenantContext::class)->runAs($r, fn () => Order::with('items')->first());
    expect($order->subtotal_cents)->toBe(1400)->and($order->items[0]->name)->toBe('Margherita · Large');
});

it('shows “from” the lowest available size on the menu and lists the sizes', function () {
    [$r, , $d] = vaShop();
    $tree = app(MenuService::class)->tree($r);
    expect($tree[0]['products'][0]['price'])->toBe(8.0)->and($tree[0]['products'][0]['variants'])->toHaveCount(2)->and($tree[0]['products'][0]['available'])->toBeTrue();

    app(TenantContext::class)->runAs($r, fn () => $d['s']->update(['is_available' => false]));
    expect(app(MenuService::class)->tree($r)[0]['products'][0]['price'])->toBe(14.0);

    app(TenantContext::class)->runAs($r, fn () => $d['l']->update(['is_available' => false]));
    expect(app(MenuService::class)->tree($r)[0]['products'][0]['available'])->toBeFalse();
});

it('edits sizes from the product form, keeping ids of rows that stay', function () {
    [$r, $owner, $d] = vaShop();
    $base = ['category_id' => $d['cat']->id, 'name' => ['en' => 'Margherita'], 'price' => 10, 'is_active' => '1', 'is_available' => '1'];

    $this->actingAs($owner)->get(route('menu.products.edit', $d['pizza']->id))->assertOk()->assertSee('Small')->assertSee('Large');
    $this->actingAs($owner)->put(route('menu.products.update', $d['pizza']->id), $base + ['variants' => [
        ['id' => $d['s']->id, 'name' => ['en' => 'Small'], 'price' => '9.00', 'is_available' => '1'],
        ['id' => '', 'name' => ['en' => 'Family'], 'price' => '20', 'is_available' => '1'],
        ['id' => '', 'name' => ['en' => ''], 'price' => '5'],          // no name: ignored
        ['id' => '', 'name' => ['en' => 'Nameless price'], 'price' => ''], // no price: ignored
    ]])->assertSessionHasNoErrors();

    $v = app(TenantContext::class)->runAs($r, fn () => $d['pizza']->variants()->get());
    expect($v->pluck('name.en')->all())->toBe(['Small', 'Family'])->and($v[0]->id)->toBe($d['s']->id)->and((float) $v[0]->price)->toBe(9.0)
        ->and(app(TenantContext::class)->runAs($r, fn () => ProductVariant::find($d['l']->id)))->toBeNull();

    $this->actingAs($owner)->put(route('menu.products.update', $d['pizza']->id), $base)->assertSessionHasNoErrors();
    expect(app(TenantContext::class)->runAs($r, fn () => $d['pizza']->variants()->count()))->toBe(0);
});

it('cannot touch the sizes of another restaurant’s dish', function () {
    [$r, $owner, $d] = vaShop();
    [$other, , $od] = vaShop();
    $this->actingAs($owner)->put(route('menu.products.update', $d['pizza']->id), ['category_id' => $d['cat']->id, 'name' => ['en' => 'M'], 'price' => 1, 'variants' => [['id' => $od['s']->id, 'name' => ['en' => 'Hijack'], 'price' => '1', 'is_available' => '1']]])->assertSessionHasNoErrors();
    expect(app(TenantContext::class)->runAs($other, fn () => $od['s']->fresh()->name['en']))->toBe('Small');
});

it('copies sizes when a dish is duplicated', function () {
    [$r, , $d] = vaShop();
    $copy = app(TenantContext::class)->runAs($r, fn () => app(MenuService::class)->duplicate($d['pizza']->load('optionGroups', 'variants')));
    expect(app(TenantContext::class)->runAs($r, fn () => $copy->variants()->pluck('price')->map(fn ($p) => (float) $p)->all()))->toBe([8.0, 14.0]);
});

it('lets staff order a size at the till', function () {
    [$r, , $d] = vaShop();
    $waiter = User::factory()->create(['restaurant_id' => $r->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
    $waiter->assignRole('waiter');
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $table = app(TenantContext::class)->runAs($r, fn () => DiningTable::create(['name' => '1']));

    $this->actingAs($waiter)->postJson(route('orders.pos.store'), ['type' => 'dine_in', 'table_id' => $table->id, 'lines' => [['product_id' => $d['pizza']->id, 'variant_id' => $d['s']->id, 'qty' => 3]]])->assertCreated();
    expect(app(TenantContext::class)->runAs($r, fn () => Order::first()->subtotal_cents))->toBe(2400);
    $this->actingAs($waiter)->postJson(route('orders.pos.store'), ['type' => 'dine_in', 'table_id' => $table->id, 'lines' => [['product_id' => $d['pizza']->id, 'qty' => 1]]])->assertStatus(422);
});

it('renders the size picker on the public page', function () {
    [$r] = vaShop();
    $this->get("/r/{$r->slug}")->assertOk()->assertSee('name="size"', false)->assertSee('Small');
});
