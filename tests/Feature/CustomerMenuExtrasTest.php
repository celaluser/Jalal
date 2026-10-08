<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Models\Banner;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    Cache::flush();
});

function cmShop(): array
{
    $r = Restaurant::create(['name' => 'Menu Bistro', 'slug' => 'cm'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now()]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $owner = User::factory()->create(['restaurant_id' => $r->id]);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($r->id);
    $owner->assignRole(Permissions::OWNER);
    $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $d = app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Food'], 'sort' => 1]);

        return ['hot' => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Fire Noodles'], 'price' => 12, 'sort' => 1, 'spice_level' => 3])];
    });

    return [$r, $owner, $d];
}

it('saves the spice level from the dish form and sends it to the menu', function () {
    [$r, $owner, $d] = cmShop();
    $this->actingAs($owner)->put(route('menu.products.update', $d['hot']->id), ['name' => ['en' => 'Fire Noodles'], 'category_id' => $d['hot']->category_id, 'price' => 12, 'spice_level' => 2, 'is_active' => 1])->assertRedirect();
    expect(app(TenantContext::class)->runAs($r, fn () => Product::find($d['hot']->id)->spice_level))->toBe(2);
    $this->actingAs($owner)->put(route('menu.products.update', $d['hot']->id), ['name' => ['en' => 'Fire Noodles'], 'category_id' => $d['hot']->category_id, 'price' => 12, 'spice_level' => 9, 'is_active' => 1])->assertSessionHasErrors('spice_level');

    $html = $this->get('/r/'.$r->slug)->assertOk()->getContent();
    expect($html)->toContain('\\u0022spice\\u0022:2')->and($html)->toContain(__('customer.filter_spice'))->toContain(__('customer.filter_favorites'))->toContain(__('customer.a11y_contrast'));
});

it('manages banners and only shows live ones to guests', function () {
    [$r, $owner] = cmShop();
    $this->actingAs($owner)->get(route('banners.index'))->assertOk();
    $this->actingAs($owner)->post(route('banners.store'), ['title' => ['en' => 'Happy hour'], 'text' => ['en' => '2 for 1 drinks'], 'link_url' => 'https://example.com/offer', 'is_active' => 1])->assertRedirect(route('banners.index'));
    $this->actingAs($owner)->post(route('banners.store'), ['title' => ['en' => 'Old news'], 'ends_on' => now()->subDays(3)->toDateString(), 'is_active' => 1])->assertRedirect();
    $this->actingAs($owner)->post(route('banners.store'), ['title' => ['en' => 'Welcome pop'], 'is_popup' => 1, 'is_active' => 1])->assertRedirect();
    $this->actingAs($owner)->post(route('banners.store'), ['title' => ['en' => 'Hidden'], 'is_active' => 0])->assertRedirect();

    $html = $this->get('/r/'.$r->slug)->assertOk()->getContent();
    expect($html)->toContain('Happy hour')->toContain('Welcome pop')->not->toContain('Old news')->not->toContain('Hidden');
    expect($html)->toContain('\\u0022popup\\u0022:true');
});

it('rejects unsafe banner links and other restaurants’ banners', function () {
    [, $owner] = cmShop();
    foreach (['javascript:alert(1)', 'http://plain.example', '//evil.example', 'data:text/html,hi'] as $bad) {
        $this->actingAs($owner)->post(route('banners.store'), ['title' => ['en' => 'X'], 'link_url' => $bad])->assertSessionHasErrors('link_url');
    }
    $this->actingAs($owner)->post(route('banners.store'), ['title' => ['en' => 'Jump'], 'link_url' => '#cat-4'])->assertSessionHasNoErrors();

    [$r2, $owner2] = cmShop();
    $other = app(TenantContext::class)->runAs($r2, fn () => Banner::create(['title' => ['en' => 'Theirs'], 'is_active' => true]));
    $this->actingAs($owner)->get(route('banners.edit', $other->id))->assertNotFound();
    $this->actingAs($owner)->delete(route('banners.destroy', $other->id))->assertNotFound();
});

it('serves the manifest, icons and a service worker that skips orders', function () {
    [$r] = cmShop();
    $base = '/r/'.$r->slug;
    $m = $this->get($base.'/manifest.webmanifest')->assertOk()->json();
    expect($m['name'])->toBe('Menu Bistro')->and($m['display'])->toBe('standalone')->and($m['start_url'])->toStartWith($base)->and($m['icons'])->toHaveCount(2);

    $icon = $this->get($base.'/pwa-icon-192.png')->assertOk();
    expect($icon->headers->get('content-type'))->toBe('image/png')->and(substr($icon->getContent(), 0, 4))->toBe("\x89PNG");
    $this->get($base.'/pwa-icon-300.png')->assertNotFound();

    $sw = $this->get($base.'/sw.js')->assertOk();
    expect($sw->headers->get('content-type'))->toContain('javascript')->and($sw->getContent())->toContain("const BASE = '{$base}'")->toContain('(order|cart|account)');

    $this->get($base)->assertSee('manifest.webmanifest', false)->assertSee('apple-touch-icon', false);
    $this->get('/r/nope/manifest.webmanifest')->assertNotFound();
});

it('lets guests view approximate prices in other currencies only when allowed and rated', function () {
    [$r, $owner] = cmShop();
    Currency::where('code', 'USD')->update(['rate' => 1]);
    Currency::create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'is_active' => true, 'rate' => 0.9]);
    $page = fn () => $this->get('/r/'.$r->slug)->assertOk()->getContent();

    expect($page())->not->toContain('\\u0022code\\u0022:\\u0022EUR');
    $this->actingAs($owner)->put(route('appearance.update'), ['theme' => 'classic', 'font' => 'sans', 'layout' => 'list', 'radius' => 'soft', 'currency_switch' => 1])->assertRedirect();
    Cache::flush();
    expect($page())->toContain('\\u0022code\\u0022:\\u0022EUR')->toContain(__('customer.currency_note', ['base' => 'USD']));

    Currency::where('code', 'USD')->update(['rate' => null]);
    expect($page())->not->toContain('\\u0022code\\u0022:\\u0022EUR');
});

it('stores a currency rate from the admin screen and rejects nonsense', function () {
    $admin = User::factory()->create(['restaurant_id' => null]);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $admin->assignRole(Permissions::SUPER_ADMIN);
    $eur = Currency::create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'is_active' => true]);
    $base = ['name' => 'Euro', 'symbol' => '€', 'symbol_position' => 'before', 'decimals' => 2, 'decimal_separator' => ',', 'thousands_separator' => '.'];

    $this->actingAs($admin)->put(route('admin.currencies.update', $eur), $base + ['rate' => '0.92'])->assertRedirect();
    expect((float) $eur->fresh()->rate)->toBe(0.92);
    $this->actingAs($admin)->put(route('admin.currencies.update', $eur), $base + ['rate' => '-3'])->assertSessionHasErrors('rate');
    $this->actingAs($admin)->put(route('admin.currencies.update', $eur), $base)->assertRedirect();
    expect($eur->fresh()->rate)->toBeNull();
});

function cmCustomer(Restaurant $r, string $email = 'sam@example.com'): Customer
{
    return app(TenantContext::class)->runAs($r, function () use ($email) {
        $c = Customer::create(['name' => 'Sam', 'email' => $email, 'phone' => '+1 555 111 2222']);
        Order::unguarded(fn () => Order::create(['number' => 1, 'token' => str_repeat('a', 24), 'type' => 'takeaway', 'status' => 'completed', 'source' => 'qr', 'customer_id' => $c->id, 'customer_name' => 'Sam', 'customer_email' => $email, 'customer_phone' => '+1 555 111 2222', 'subtotal_cents' => 1000, 'total_cents' => 1000, 'currency_code' => 'USD']));

        return $c;
    });
}

it('signs a guest in by e-mailed link and shows their orders', function () {
    Mail::fake();
    [$r] = cmShop();
    $c = cmCustomer($r);
    $base = '/r/'.$r->slug;

    $this->get($base.'/account')->assertOk()->assertSee(__('customer.account_send'));
    $this->post($base.'/account/login', ['email' => 'sam@example.com'])->assertSessionHas('link_sent');
    Mail::assertSent(TemplatedMail::class, fn ($m) => $m->hasTo('sam@example.com') && str_contains(json_encode($m->vars), 'account%2Fverify') || str_contains(json_encode($m->vars), 'account\/verify'));

    $link = URL::temporarySignedRoute('storefront.account.verify', now()->addMinutes(30), ['restaurant' => $r->slug, 'customer' => $c->id]);
    $this->get($link)->assertRedirect($base.'/account');
    $this->get($base.'/account')->assertOk()->assertSee('sam@example.com')->assertSee('#1')->assertSee('$10.00');

    $this->put($base.'/account', ['name' => 'Samuel', 'phone' => '+1 555 999 8888'])->assertSessionHasNoErrors();
    expect(app(TenantContext::class)->runAs($r, fn () => Customer::find($c->id)->name))->toBe('Samuel');
    $this->put($base.'/account', ['name' => 'S', 'phone' => 'abc'])->assertSessionHasErrors('phone');

    // The menu pre-fills the checkout from the account.
    $this->get($base)->assertSee('Samuel');

    $this->post($base.'/account/logout')->assertRedirect();
    $this->get($base.'/account')->assertSee(__('customer.account_send'));
});

it('does not reveal which addresses have ordered, and refuses bad links', function () {
    Mail::fake();
    [$r] = cmShop();
    cmCustomer($r);
    $base = '/r/'.$r->slug;
    $this->post($base.'/account/login', ['email' => 'nobody@example.com'])->assertSessionHas('link_sent');
    Mail::assertNothingSent();
    $this->post($base.'/account/login', ['email' => 'not-an-email'])->assertSessionHasErrors('email');

    $c = app(TenantContext::class)->runAs($r, fn () => Customer::first());
    $this->get($base.'/account/verify/'.$c->id)->assertForbidden(); // unsigned
    $expired = URL::temporarySignedRoute('storefront.account.verify', now()->subMinute(), ['restaurant' => $r->slug, 'customer' => $c->id]);
    $this->get($expired)->assertForbidden();
    $this->put($base.'/account', ['name' => 'X'])->assertForbidden();
});

it('keeps accounts separate between restaurants', function () {
    [$r1] = cmShop();
    [$r2] = cmShop();
    $c1 = cmCustomer($r1);
    // A link made for restaurant 1's customer does not work on restaurant 2's menu.
    $link = URL::temporarySignedRoute('storefront.account.verify', now()->addMinutes(30), ['restaurant' => $r2->slug, 'customer' => $c1->id]);
    $this->get($link)->assertNotFound();
});

it('lets a signed-in guest erase their data', function () {
    [$r] = cmShop();
    $c = cmCustomer($r);
    $base = '/r/'.$r->slug;
    $this->get(URL::temporarySignedRoute('storefront.account.verify', now()->addMinutes(30), ['restaurant' => $r->slug, 'customer' => $c->id]));
    $this->delete($base.'/account')->assertRedirect();
    expect(app(TenantContext::class)->runAs($r, fn () => [Customer::count(), Order::first()->customer_email]))->toBe([0, null]);
});
