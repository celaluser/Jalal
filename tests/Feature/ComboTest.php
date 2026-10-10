<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
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

function cbShop(): array
{
    $r = Restaurant::create(['name' => 'Combo', 'slug' => 'cb'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'onboarded_at' => now()]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => [], 'features' => []]), now()->addMonth());
    $owner = User::factory()->create(['restaurant_id' => $r->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
    $owner->assignRole(Permissions::OWNER);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $d = app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Food'], 'sort' => 1]);
        $mk = fn ($n, $p, $extra = []) => Product::create(array_merge(['category_id' => $cat->id, 'name' => ['en' => $n], 'price' => $p, 'sort' => 1], $extra));
        $burger = $mk('Burger', 9);
        $wrap = $mk('Wrap', 8);
        $fries = $mk('Fries', 3);
        $cola = $mk('Cola', 2, ['stock_qty' => 5]);
        $shake = $mk('Shake', 4);
        $combo = $mk('Lunch Set', 11, ['is_combo' => true]);
        $main = $combo->comboSlots()->create(['name' => ['en' => 'Main'], 'sort' => 0]);
        $main->items()->create(['product_id' => $burger->id, 'price_delta' => 0, 'sort' => 0]);
        $main->items()->create(['product_id' => $wrap->id, 'price_delta' => 0.5, 'sort' => 1]);
        $drink = $combo->comboSlots()->create(['name' => ['en' => 'Drink'], 'sort' => 1]);
        $drink->items()->create(['product_id' => $cola->id, 'price_delta' => 0, 'sort' => 0]);
        $drink->items()->create(['product_id' => $shake->id, 'price_delta' => 1.5, 'sort' => 1]);

        return compact('cat', 'burger', 'wrap', 'fries', 'cola', 'shake', 'combo', 'main', 'drink');
    });

    return [$r, $owner, $d];
}

function cbLine(array $d, array $picks, int $qty = 1): array
{
    return ['product_id' => $d['combo']->id, 'qty' => $qty, 'combo' => [$d['main']->id => $picks[0] ?? null, $d['drink']->id => $picks[1] ?? null]];
}

it('prices a set menu from its base price plus the surcharge of the choices', function () {
    [$r, , $d] = cbShop();
    $l = fn ($picks, $qty = 1) => $this->postJson("/r/{$r->slug}/cart/quote", ['lines' => [cbLine($d, $picks, $qty)]])->json('lines.0');

    expect($l([$d['burger']->id, $d['cola']->id])['unit_cents'])->toBe(1100)
        ->and($l([$d['wrap']->id, $d['shake']->id], 2)['unit_cents'])->toBe(1100 + 50 + 150)->and($l([$d['wrap']->id, $d['shake']->id], 2)['total_cents'])->toBe(2600);
    $options = $l([$d['wrap']->id, $d['shake']->id])['options'];
    expect(collect($options)->map(fn ($o) => $o['group'].': '.$o['name'])->all())->toBe(['Main: Wrap', 'Drink: Shake']);
});

it('needs a pick for every slot and rejects a dish that is not offered there', function () {
    [$r, , $d] = cbShop();
    $errors = fn ($picks) => $this->postJson("/r/{$r->slug}/cart/quote", ['lines' => [cbLine($d, $picks)]])->json('lines.0.errors');

    expect($errors([$d['burger']->id]))->toBe(['combo_required'])->and($errors([]))->toBe(['combo_required'])
        ->and($errors([$d['fries']->id, $d['cola']->id]))->toBe(['combo_required']); // fries are not in the "Main" slot
});

it('refuses a pick that is sold out', function () {
    [$r, , $d] = cbShop();
    app(TenantContext::class)->runAs($r, fn () => $d['cola']->update(['stock_qty' => 0]));
    expect($this->postJson("/r/{$r->slug}/cart/quote", ['lines' => [cbLine($d, [$d['burger']->id, $d['cola']->id])]])->json('lines.0.errors'))->toContain('sold_out');
});

it('orders a set menu, snapshots the picks and uses up the stock of the picked dishes', function () {
    [$r, , $d] = cbShop();
    $this->postJson("/r/{$r->slug}/order", ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => [cbLine($d, [$d['burger']->id, $d['cola']->id], 2)]])->assertCreated();

    $order = app(TenantContext::class)->runAs($r, fn () => Order::with('items')->first());
    expect($order->subtotal_cents)->toBe(2200)->and($order->items[0]->name)->toBe('Lunch Set')->and(collect($order->items[0]->options)->pluck('name')->all())->toBe(['Burger', 'Cola'])
        ->and(app(TenantContext::class)->runAs($r, fn () => $d['cola']->fresh()->stock_qty))->toBe(3);

    // The last cola cannot be sold three times.
    $this->postJson("/r/{$r->slug}/order", ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => [cbLine($d, [$d['burger']->id, $d['cola']->id], 4)]])->assertStatus(422);
    expect(app(TenantContext::class)->runAs($r, fn () => $d['cola']->fresh()->stock_qty))->toBe(3);
});

it('lists the slots on the menu and hides a combo whose slot is empty', function () {
    [$r, , $d] = cbShop();
    $row = collect(app(MenuService::class)->tree($r)[0]['products'])->firstWhere('id', $d['combo']->id);
    expect($row['combo'])->toHaveCount(2)->and($row['combo'][0]['items'][1])->toMatchArray(['name' => 'Wrap', 'delta' => 0.5, 'available' => true]);
});

it('builds a combo from the product form and flattens a combo with no usable slots', function () {
    [$r, $owner, $d] = cbShop();
    $plain = app(TenantContext::class)->runAs($r, fn () => Product::create(['category_id' => $d['cat']->id, 'name' => ['en' => 'Kids Set'], 'price' => 7, 'sort' => 9]));
    $base = ['category_id' => $d['cat']->id, 'name' => ['en' => 'Kids Set'], 'price' => 7, 'is_active' => '1', 'is_available' => '1', 'is_combo' => '1'];

    $this->actingAs($owner)->put(route('menu.products.update', $plain->id), $base + ['combo' => ['slots' => [
        ['name' => ['en' => 'Toy'], 'items' => [['product_id' => $d['fries']->id, 'price_delta' => '0'], ['product_id' => $d['fries']->id, 'price_delta' => '1'], ['product_id' => $plain->id, 'price_delta' => '0']]],
        ['name' => ['en' => ''], 'items' => [['product_id' => $d['cola']->id]]],         // no name: dropped
        ['name' => ['en' => 'Empty'], 'items' => []],                                      // no dishes: dropped
    ]]])->assertSessionHasNoErrors();
    $slots = app(TenantContext::class)->runAs($r, fn () => $plain->comboSlots()->with('items')->get());
    expect($slots)->toHaveCount(1)->and($slots[0]->items->pluck('product_id')->all())->toBe([$d['fries']->id]); // duplicate and itself ignored

    $this->actingAs($owner)->get(route('menu.products.edit', $plain->id))->assertOk()->assertSee('Toy');
    $this->actingAs($owner)->put(route('menu.products.update', $plain->id), array_diff_key($base, ['is_combo' => 1]) + ['combo' => ['slots' => [['name' => ['en' => 'Toy'], 'items' => [['product_id' => $d['fries']->id]]]]]])->assertSessionHasNoErrors();
    expect(app(TenantContext::class)->runAs($r, fn () => $plain->comboSlots()->count()))->toBe(0);
});

it('does not accept another restaurant’s dish in a slot', function () {
    [$r, $owner, $d] = cbShop();
    [, , $od] = cbShop();
    $this->actingAs($owner)->put(route('menu.products.update', $d['combo']->id), ['category_id' => $d['cat']->id, 'name' => ['en' => 'Lunch Set'], 'price' => 11, 'is_combo' => '1', 'combo' => ['slots' => [['name' => ['en' => 'X'], 'items' => [['product_id' => $od['burger']->id]]]]]])->assertSessionHasErrors();
});

it('copies a combo when the dish is duplicated', function () {
    [$r, , $d] = cbShop();
    $copy = app(TenantContext::class)->runAs($r, fn () => app(MenuService::class)->duplicate($d['combo']->load('optionGroups', 'variants', 'comboSlots')));
    expect(app(TenantContext::class)->runAs($r, fn () => $copy->comboSlots()->with('items')->get()->map(fn ($s) => $s->items->count())->all()))->toBe([2, 2]);
});

it('lets staff ring up a set menu at the till', function () {
    [$r, , $d] = cbShop();
    $w = User::factory()->create(['restaurant_id' => $r->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
    $w->assignRole('waiter');
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $table = app(TenantContext::class)->runAs($r, fn () => DiningTable::create(['name' => '1']));
    $this->actingAs($w)->postJson(route('orders.pos.store'), ['type' => 'dine_in', 'table_id' => $table->id, 'lines' => [cbLine($d, [$d['wrap']->id, $d['cola']->id])]])->assertCreated();
    expect(app(TenantContext::class)->runAs($r, fn () => Order::first()->subtotal_cents))->toBe(1150);
});
