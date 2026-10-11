<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Payments\GatewayManager;
use App\Modules\Billing\Services\CouponService;
use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Billing\Services\PaymentProcessor;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Menu\Services\ThemeRegistry;
use App\Modules\Store\Models\Entitlement;
use App\Modules\Store\Services\Catalog;
use App\Modules\Store\Services\Entitlements;
use App\Modules\Store\Services\StoreAccess;
use App\Modules\Store\Services\StoreCheckout;
use App\Modules\Tenancy\Models\Restaurant;
use App\Modules\Tenancy\Services\DomainService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    app(GatewayManager::class)->save('bank_transfer', true, ['instructions' => 'IBAN :reference']);
});

afterEach(fn () => Carbon::setTestNow());

function stoShop(array $features = [], string $role = Permissions::OWNER): array
{
    $r = Restaurant::create(['name' => 'Store '.uniqid(), 'slug' => 'st'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now()]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => [], 'features' => $features]), now()->addYear());
    $u = User::factory()->create(['restaurant_id' => $r->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
    $u->assignRole($role);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return [$r, $u];
}

function stoAdmin(): User
{
    $a = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $a->assignRole(Permissions::SUPER_ADMIN);

    return $a;
}

function stoSell(string $slug, array $over = []): void
{
    app(Catalog::class)->save($slug, $over + ['mode' => 'paid', 'billing' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'trial_days' => 0, 'visible' => 1]);
}

function stoBuy(Restaurant $r, string $slug, int $units = 1): void
{
    $out = app(StoreCheckout::class)->start($r, $slug, $units, 'bank_transfer');
    app(PaymentProcessor::class)->settle($out['invoice'], 'manual');
}

it('keeps today’s behaviour until the platform changes something', function () {
    $c = app(Catalog::class);
    expect($c->find('feature:analytics')['mode'])->toBe('plan')->and($c->find('feature:subdomain')['mode'])->toBe('free')->and($c->find('theme:aurora')['mode'])->toBe('free');

    [$r] = stoShop(['analytics' => true]);
    $g = app(LimitGuard::class);
    expect($g->hasFeature($r, 'analytics'))->toBeTrue()->and($g->hasFeature($r, 'api'))->toBeFalse()->and($g->hasFeature($r, 'subdomain'))->toBeTrue();
});

it('opens a feature for everyone when the platform makes it free', function () {
    [$r] = stoShop();
    expect(app(LimitGuard::class)->hasFeature($r, 'api'))->toBeFalse();

    app(Catalog::class)->save('feature:api', ['mode' => 'free', 'billing' => 'monthly', 'price' => 0, 'visible' => 1]);
    expect(app(LimitGuard::class)->hasFeature($r, 'api'))->toBeTrue();
});

it('sells a feature: buying unlocks it, a plan that includes it needs no purchase, other restaurants stay locked', function () {
    stoSell('feature:api');
    [$a] = stoShop();
    [$b] = stoShop();
    [$withPlan] = stoShop(['api' => true]);

    expect(app(LimitGuard::class)->hasFeature($a, 'api'))->toBeFalse();
    stoBuy($a, 'feature:api');

    expect(app(LimitGuard::class)->hasFeature($a, 'api'))->toBeTrue()->and(app(LimitGuard::class)->hasFeature($b, 'api'))->toBeFalse()->and(app(LimitGuard::class)->hasFeature($withPlan, 'api'))->toBeTrue();
    expect(Entitlement::allTenants()->where('restaurant_id', $a->id)->first())->toMatchArray(['item_slug' => 'feature:api', 'source' => 'purchase']);
});

it('rents by the month, extends from the end date, and stops when it runs out', function () {
    stoSell('feature:api', ['price' => 5]);
    [$r] = stoShop();
    Carbon::setTestNow('2026-01-10 12:00:00');

    stoBuy($r, 'feature:api', 3);
    expect(Entitlement::allTenants()->first()->ends_at->toDateString())->toBe('2026-04-10');
    stoBuy($r, 'feature:api', 1);
    expect(Entitlement::allTenants()->count())->toBe(1)->and(Entitlement::allTenants()->first()->ends_at->toDateString())->toBe('2026-05-10');

    $invoice = Invoice::allTenants()->latest('id')->first();
    expect((float) $invoice->total)->toBe(5.0)->and($invoice->store_slug)->toBe('feature:api');

    Carbon::setTestNow('2026-05-11 12:00:00');
    app(Entitlements::class)->forget();
    expect(app(LimitGuard::class)->hasFeature($r, 'api'))->toBeFalse();
    $this->artisan('store:expire')->assertSuccessful();
    expect(Entitlement::allTenants()->first()->status)->toBe('expired');
});

it('prices by quantity and sells one-time items for good', function () {
    stoSell('theme:neon', ['billing' => 'one_time', 'price' => 29]);
    stoSell('feature:api', ['billing' => 'yearly', 'price' => 50]);
    [$r] = stoShop();

    expect((float) app(StoreCheckout::class)->quote(app(Catalog::class)->find('feature:api'), 2)['price'])->toBe(100.0);
    stoBuy($r, 'theme:neon');
    expect(Entitlement::allTenants()->first()->ends_at)->toBeNull();
    expect(fn () => app(StoreCheckout::class)->start($r, 'feature:analytics', 1, 'bank_transfer'))->toThrow(BillingException::class);
});

it('locks a paid theme until it is owned or a plan unlocks all themes', function () {
    stoSell('theme:aurora');
    [$r, $owner] = stoShop(); // classic is the default theme
    $r->update(['theme' => 'aurora']);

    expect(app(ThemeRegistry::class)->settings($r->fresh())['theme'])->toBe('classic');
    $this->actingAs($owner)->put(route('appearance.update'), ['theme' => 'aurora', 'font' => 'sans', 'layout' => 'cards', 'radius' => 'round'])->assertSessionHasErrors('theme');

    stoBuy($r, 'theme:aurora');
    expect(app(StoreAccess::class)->themeAllowed($r, 'aurora'))->toBeTrue();
    expect(app(ThemeRegistry::class)->settings($r->fresh())['theme'])->toBe('aurora');
    $this->actingAs($owner)->put(route('appearance.update'), ['theme' => 'aurora', 'font' => 'sans', 'layout' => 'cards', 'radius' => 'round'])->assertSessionHasNoErrors();

    [$unlocked] = stoShop(['premium_themes' => true]);
    expect(app(StoreAccess::class)->themeAllowed($unlocked, 'aurora'))->toBeTrue();
});

it('gives a trial once per item', function () {
    stoSell('feature:api', ['trial_days' => 7]);
    [$r, $owner] = stoShop();

    $this->actingAs($owner)->post(route('store.trial', 'feature:api'))->assertRedirect();
    expect(app(LimitGuard::class)->hasFeature($r, 'api'))->toBeTrue();
    expect(Entitlement::allTenants()->first()->ends_at->isFuture())->toBeTrue();
    $this->actingAs($owner)->post(route('store.trial', 'feature:api'))->assertSessionHasErrors('checkout');
    expect(Entitlement::allTenants()->count())->toBe(1);
});

it('shows the restaurant store, takes a purchase and keeps staff without billing rights out', function () {
    stoSell('feature:api');
    [$r, $owner] = stoShop();
    [, $waiter] = stoShop([], Permissions::WAITER);

    $this->actingAs($owner)->get(route('store.index'))->assertOk()->assertSee('Themes');
    $this->actingAs($owner)->get(route('store.index', ['tab' => 'features']))->assertOk()->assertSee('REST API');
    $this->actingAs($owner)->get(route('store.show', 'feature:api'))->assertOk()->assertSee('Rent now');
    $this->actingAs($owner)->get(route('store.show', 'feature:nope'))->assertNotFound();

    $this->actingAs($owner)->post(route('store.buy', 'feature:api'), ['gateway' => 'bank_transfer', 'units' => 3])->assertRedirect(route('store.show', 'feature:api'))->assertSessionHas('instructions');
    expect(Invoice::allTenants()->where('restaurant_id', $r->id)->whereNotNull('store_slug')->first())->toMatchArray(['status' => 'open'])
        ->and(app(LimitGuard::class)->hasFeature($r, 'api'))->toBeFalse();

    $this->actingAs($owner)->post(route('store.buy', 'feature:api'), ['gateway' => 'bank_transfer', 'units' => 7])->assertSessionHasErrors('units');
    $this->actingAs($waiter)->get(route('store.index'))->assertOk();
    $this->actingAs($waiter)->post(route('store.buy', 'feature:api'), ['gateway' => 'bank_transfer'])->assertForbidden();
});

it('lets the super admin price, hide, reset, give and take back items', function () {
    $admin = stoAdmin();
    [$r] = stoShop();

    $this->actingAs($admin)->get(route('admin.store.index'))->assertOk()->assertSee('Custom domain')->assertSee('Aurora');
    $this->actingAs($admin)->get(route('admin.store.edit', 'feature:custom_domain'))->assertOk();

    $this->actingAs($admin)->put(route('admin.store.update', 'feature:custom_domain'), ['mode' => 'paid', 'billing' => 'yearly', 'price' => 0, 'currency_code' => 'USD'])->assertSessionHasErrors('price');
    $this->actingAs($admin)->put(route('admin.store.update', 'feature:custom_domain'), ['mode' => 'paid', 'billing' => 'yearly', 'price' => 49, 'currency_code' => 'USD', 'trial_days' => 3, 'name' => '<b>Own domain</b>', 'summary' => 'Your address', 'visible' => 1])->assertRedirect(route('admin.store.index'));
    $item = app(Catalog::class)->find('feature:custom_domain');
    expect($item['mode'])->toBe('paid')->and($item['price'])->toBe(49.0)->and($item['name'])->toBe('Own domain')->and($item['trial_days'])->toBe(3);

    $this->actingAs($admin)->post(route('admin.store.grant'), ['restaurant_id' => $r->id, 'item' => 'feature:custom_domain', 'months' => 2])->assertRedirect();
    $e = Entitlement::allTenants()->first();
    expect($e->source)->toBe('admin')->and(app(LimitGuard::class)->hasFeature($r, 'custom_domain'))->toBeTrue();
    $this->actingAs($admin)->get(route('admin.store.sales'))->assertOk()->assertSee($r->name);

    $this->actingAs($admin)->delete(route('admin.store.revoke', $e->id))->assertRedirect();
    expect(app(LimitGuard::class)->hasFeature($r, 'custom_domain'))->toBeFalse();

    $this->actingAs($admin)->delete(route('admin.store.reset', 'feature:custom_domain'))->assertRedirect();
    expect(app(Catalog::class)->find('feature:custom_domain')['mode'])->toBe('plan');

    [, $owner] = stoShop();
    $this->actingAs($owner)->get(route('admin.store.index'))->assertForbidden();
});

it('can make the own subdomain a paid feature', function () {
    config(['tenancy.subdomains_enabled' => true, 'tenancy.base_domain' => 'menu.test']);
    [$r] = stoShop();
    $domains = app(DomainService::class);
    $domains->setSubdomain($r, 'free-one');
    expect($r->fresh()->subdomain)->toBe('free-one');

    stoSell('feature:subdomain');
    app(Entitlements::class)->forget();
    expect(fn () => $domains->setSubdomain($r, 'second-try'))->toThrow(InvalidArgumentException::class);

    stoBuy($r, 'feature:subdomain');
    $domains->setSubdomain($r, 'second-try');
    expect($r->fresh()->subdomain)->toBe('second-try');
});

it('hides items the platform switched off from the store but keeps them for owners', function () {
    stoSell('feature:api', ['visible' => 0]);
    [$r, $owner] = stoShop();
    $this->actingAs($owner)->get(route('store.show', 'feature:api'))->assertNotFound();

    app(Entitlements::class)->grant($r, 'feature:api', null, 'admin');
    $this->actingAs($owner)->get(route('store.show', 'feature:api'))->assertOk();
});

it('takes a store coupon off the invoice and keeps plan coupons out of the store', function () {
    stoSell('feature:api', ['price' => 20]);
    [$r, $owner] = stoShop();
    $coupon = Coupon::create(['code' => 'STORE25', 'type' => 'percent', 'value' => 25, 'applies_to' => 'store', 'is_active' => true]);
    Coupon::create(['code' => 'PLANONLY', 'type' => 'percent', 'value' => 50, 'applies_to' => 'plans', 'is_active' => true]);
    Coupon::create(['code' => 'THEMEONLY', 'type' => 'percent', 'value' => 50, 'applies_to' => 'store', 'store_slugs' => ['theme:aurora'], 'is_active' => true]);

    $this->actingAs($owner)->post(route('store.buy', 'feature:api'), ['gateway' => 'bank_transfer', 'coupon' => 'PLANONLY'])->assertSessionHasErrors('checkout');
    $this->actingAs($owner)->post(route('store.buy', 'feature:api'), ['gateway' => 'bank_transfer', 'coupon' => 'THEMEONLY'])->assertSessionHasErrors('checkout');
    $this->actingAs($owner)->post(route('store.buy', 'feature:api'), ['gateway' => 'bank_transfer', 'coupon' => 'store25'])->assertSessionHasNoErrors();

    $invoice = Invoice::allTenants()->where('restaurant_id', $r->id)->whereNotNull('store_slug')->latest('id')->first();
    expect((float) $invoice->total)->toBe(15.0)->and($invoice->coupon_code)->toBe('STORE25');
    app(PaymentProcessor::class)->settle($invoice, 'manual');
    expect($coupon->fresh()->used_count)->toBe(1)->and(app(LimitGuard::class)->hasFeature($r, 'api'))->toBeTrue();

    // A plan checkout cannot use a store-only coupon.
    expect(fn () => app(CouponService::class)->validate('STORE25', Plan::first()))->toThrow(BillingException::class);
});

it('sells a bundle: one price, every item inside unlocked for the same time', function () {
    stoSell('theme:aurora', ['price' => 5]);
    stoSell('feature:api', ['price' => 8]);
    $admin = stoAdmin();
    [$r, $owner] = stoShop();

    $this->actingAs($admin)->post(route('admin.store.bundles.store'), ['name' => 'Pro pack', 'billing' => 'monthly', 'price' => 9, 'currency_code' => 'USD', 'items' => ['theme:aurora']])->assertSessionHasErrors('items');
    $this->actingAs($admin)->post(route('admin.store.bundles.store'), ['name' => 'Pro pack', 'billing' => 'monthly', 'price' => 9, 'currency_code' => 'USD', 'summary' => 'Everything', 'visible' => 1, 'items' => ['theme:aurora', 'feature:api', 'bundle:nope']])->assertRedirect();
    $bundle = collect(app(Catalog::class)->all())->first(fn ($i) => $i['kind'] === 'bundle');
    expect($bundle['name'])->toBe('Pro pack')->and($bundle['items'])->toBe(['theme:aurora', 'feature:api'])->and(app(Catalog::class)->bundleWorth($bundle, 'USD'))->toBe(13.0);

    $this->actingAs($owner)->get(route('store.index', ['tab' => 'bundles']))->assertOk()->assertSee('Pro pack');
    $this->actingAs($owner)->get(route('store.show', $bundle['slug']))->assertOk()->assertSee('You save');

    Carbon::setTestNow('2026-03-01 10:00:00');
    stoBuy($r, $bundle['slug'], 3);
    expect(app(LimitGuard::class)->hasFeature($r, 'api'))->toBeTrue()->and(app(StoreAccess::class)->themeAllowed($r, 'aurora'))->toBeTrue();
    expect(Entitlement::allTenants()->get()->map(fn ($e) => $e->item_slug.' '.$e->ends_at->toDateString())->sort()->values()->all())->toBe(['bundle:'.substr($bundle['slug'], 7).' 2026-06-01', 'feature:api 2026-06-01', 'theme:aurora 2026-06-01']);

    $this->actingAs($admin)->delete(route('admin.store.reset', $bundle['slug']))->assertRedirect();
    expect(app(Catalog::class)->find($bundle['slug']))->toBeNull();
});

it('reminds owners a week before a rental ends, once, and again after it is extended', function () {
    stoSell('feature:api');
    [$r, $owner] = stoShop();
    $r->forceFill(['owner_id' => $owner->id])->save();
    Mail::fake();
    Carbon::setTestNow('2026-06-01 09:00:00');
    stoBuy($r, 'feature:api', 1); // ends 2026-07-01

    Carbon::setTestNow('2026-06-20 09:00:00');
    $this->artisan('store:remind')->assertSuccessful();
    Mail::assertNotSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'store_expiring');

    Carbon::setTestNow('2026-06-26 09:00:00');
    $this->artisan('store:remind')->assertSuccessful();
    $this->artisan('store:remind')->assertSuccessful();
    expect(Mail::sent(TemplatedMail::class, fn ($m) => $m->templateKey === 'store_expiring')->count())->toBe(1);

    stoBuy($r, 'feature:api', 1); // extended to 2026-08-01: re-armed
    expect(Entitlement::allTenants()->first()->reminded_at)->toBeNull();
});
