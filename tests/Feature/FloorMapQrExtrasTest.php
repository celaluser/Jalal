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
use App\Modules\Menu\Services\ThemeRegistry;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
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

function fmShop(string $role = Permissions::OWNER): array
{
    $r = Restaurant::create(['name' => 'Map Bistro', 'slug' => 'mp'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'onboarded_at' => now()]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $user = User::factory()->create(['restaurant_id' => $r->id]);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($r->id);
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $d = app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Food'], 'sort' => 1]);

        return ['pizza' => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Pizza'], 'price' => 10, 'sort' => 1]),
            't1' => DiningTable::create(['name' => 'T1', 'sort' => 1]), 't2' => DiningTable::create(['name' => 'T2', 'sort' => 2])];
    });

    return [$r, $user, $d];
}

function fmOrder(Restaurant $r, array $d, int $tableId, string $source = 'qr')
{
    return app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, ['type' => 'dine_in', 'table_id' => $tableId, 'payment_method' => 'cash', 'customer_name' => 'G', 'lines' => [['product_id' => $d['pizza']->id, 'qty' => 1]]], $source));
}

it('shows the floor map and saves table positions and shapes', function () {
    [$r, $owner, $d] = fmShop();
    $this->actingAs($owner)->get(route('tables.map'))->assertOk()->assertSee('T1')->assertSee('T2');

    $this->actingAs($owner)->putJson(route('tables.map.save'), ['tables' => [['id' => $d['t1']->id, 'x' => 250, 'y' => 400, 'shape' => 'round']]])->assertOk()->assertJsonPath('saved', 1);
    $t = app(TenantContext::class)->runAs($r, fn () => DiningTable::find($d['t1']->id));
    expect([$t->map_x, $t->map_y, $t->shape])->toBe([250, 400, 'round']);

    $this->actingAs($owner)->putJson(route('tables.map.save'), ['tables' => [['id' => $d['t1']->id, 'x' => 5000, 'shape' => 'hex']]])->assertStatus(422);
});

it('cannot move the tables of another restaurant', function () {
    [, $owner] = fmShop();
    [$r2, , $d2] = fmShop();
    $this->actingAs($owner)->putJson(route('tables.map.save'), ['tables' => [['id' => $d2['t1']->id, 'x' => 10, 'y' => 10]]])->assertOk();
    expect(app(TenantContext::class)->runAs($r2, fn () => DiningTable::find($d2['t1']->id)->map_x))->toBeNull();
});

it('only lets table managers arrange; waiters see the map but not the save route', function () {
    [, $waiter, $d] = fmShop(Permissions::WAITER);
    $this->actingAs($waiter)->get(route('tables.map'))->assertOk();
    $this->actingAs($waiter)->putJson(route('tables.map.save'), ['tables' => [['id' => $d['t1']->id, 'x' => 1, 'y' => 1]]])->assertForbidden();
    $kitchen = fmShop(Permissions::KITCHEN)[1];
    $this->actingAs($kitchen)->get(route('tables.map'))->assertForbidden();
});

it('reports table states from open and unpaid orders', function () {
    [$r, $owner, $d] = fmShop();
    $order = fmOrder($r, $d, $d['t1']->id);
    $json = $this->actingAs($owner)->getJson(route('tables.map.status'))->assertOk()->json('states');
    expect($json[$d['t1']->id]['state'])->toBe('busy')->and($json)->not->toHaveKey((string) $d['t2']->id);

    app(TenantContext::class)->runAs($r, fn () => Order::find($order->id)->forceFill(['status' => 'ready'])->save());
    expect($this->actingAs($owner)->getJson(route('tables.map.status'))->json('states.'.$d['t1']->id.'.state'))->toBe('ready');

    app(TenantContext::class)->runAs($r, fn () => Order::find($order->id)->forceFill(['status' => 'completed', 'completed_at' => now()])->save());
    expect($this->actingAs($owner)->getJson(route('tables.map.status'))->json('states.'.$d['t1']->id.'.state'))->toBe('unpaid');

    app(TenantContext::class)->runAs($r, fn () => Order::find($order->id)->forceFill(['paid_at' => now()])->save());
    expect($this->actingAs($owner)->getJson(route('tables.map.status'))->json('states'))->toBeEmpty();
});

it('preselects the table in the till when opened from the map', function () {
    [, $owner, $d] = fmShop();
    $this->actingAs($owner)->get(route('orders.pos.index', ['table' => $d['t2']->id]))->assertOk()->assertSee('preselect', false);
});

it('saves a QR frame and paper layout and prints every layout as a PDF', function () {
    [, $owner] = fmShop();
    $this->actingAs($owner)->put(route('tables.qr.update'), ['fg' => '#000000', 'bg' => '#ffffff', 'shape' => 'square', 'frame' => 'ribbon', 'template' => 'tent'])->assertRedirect();
    $this->actingAs($owner)->get(route('tables.qr'))->assertOk()->assertSee(__('tables.frame_ribbon'));

    // Every layout once, every frame once (the route is throttled to 10 a minute).
    foreach ([['cards', 'none'], ['compact', 'border'], ['sticker', 'badge'], ['tent', 'ribbon'], ['poster', 'none'], ['cards', 'ribbon'], ['cards', 'badge']] as [$template, $frame]) {
        $res = $this->actingAs($owner)->get(route('tables.qr.pdf', ['template' => $template, 'frame' => $frame]))->assertOk();
        expect($res->headers->get('content-type'))->toContain('pdf');
    }

    $this->actingAs($owner)->put(route('tables.qr.update'), ['fg' => '#000000', 'bg' => '#ffffff', 'shape' => 'square', 'frame' => 'zigzag'])->assertSessionHasErrors('frame');
});

it('lists NFC links and exports them safely as CSV', function () {
    [$r, $owner, $d] = fmShop();
    app(TenantContext::class)->runAs($r, fn () => $d['t2']->update(['name' => '=cmd|calc']));
    $this->actingAs($owner)->get(route('tables.nfc'))->assertOk()->assertSee($d['t1']->token, false);
    $csv = $this->actingAs($owner)->get(route('tables.nfc.csv'))->assertOk()->getContent();
    expect($csv)->toContain('"T1"')->toContain('"\'=cmd|calc"')->toContain($d['t1']->token);
});

it('offers the sixth theme and the new menu options, and rejects bad values', function () {
    [$r, $owner] = fmShop();
    expect(array_keys(app(ThemeRegistry::class)->themes()))->toContain('ocean');
    $base = ['theme' => 'ocean', 'font' => 'sans', 'layout' => 'cards', 'radius' => 'round'];
    $this->actingAs($owner)->put(route('appearance.update'), $base + ['hero' => 'compact', 'scroll' => 'infinite', 'dark_toggle' => 1])->assertRedirect();
    $s = app(ThemeRegistry::class)->settings($r->fresh());
    expect([$s['theme'], $s['hero'], $s['scroll'], $s['dark_toggle']])->toBe(['ocean', 'compact', 'infinite', true]);
    $this->actingAs($owner)->put(route('appearance.update'), $base + ['hero' => 'giant'])->assertSessionHasErrors('hero');

    // The guest menu carries the toggle button and the alternative palette.
    $html = $this->get('/r/'.$r->slug)->assertOk()->assertSee(__('customer.toggle_theme'))->getContent();
    expect($html)->toContain('data-menu-alt')->and($html)->not->toContain(__('customer.hero_title'));
});

it('puts the menu in kiosk mode and tags kiosk orders, without delivery', function () {
    [$r, , $d] = fmShop();
    $this->get('/r/'.$r->slug.'?kiosk=1')->assertOk()->assertSee(__('customer.kiosk_thanks'))->assertSee('noindex', false);
    $this->get('/r/'.$r->slug)->assertOk()->assertDontSee(__('customer.kiosk_thanks'));

    $payload = ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'K', 'customer_phone' => '+1 555 111 2222', 'kiosk' => 1, 'lines' => [['product_id' => $d['pizza']->id, 'qty' => 1]]];
    $this->postJson('/r/'.$r->slug.'/order', $payload)->assertCreated();
    expect(app(TenantContext::class)->runAs($r, fn () => Order::first()->source))->toBe('kiosk');

    $this->postJson('/r/'.$r->slug.'/order', ['type' => 'delivery', 'delivery_address' => '1 Long Street'] + $payload)->assertStatus(422);
});

it('toggles tablet mode for staff', function () {
    [, $owner] = fmShop();
    $this->actingAs($owner)->get(route('tables.index'))->assertOk()->assertDontSee('data-tablet', false);
    $this->actingAs($owner)->post(route('tablet.toggle'))->assertRedirect();
    $this->actingAs($owner)->get(route('tables.index'))->assertOk()->assertSee('data-tablet', false)->assertSee('wakeLock', false);
    $this->actingAs($owner)->post(route('tablet.toggle'));
    $this->actingAs($owner)->get(route('tables.index'))->assertDontSee('data-tablet', false);
});
