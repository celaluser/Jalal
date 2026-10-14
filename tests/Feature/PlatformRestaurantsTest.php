<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);

    $this->admin = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $this->admin->assignRole(Permissions::SUPER_ADMIN);
});

function prPlan(array $limits = ['tables' => 5], array $features = []): Plan
{
    return Plan::create(['name' => 'Starter', 'slug' => 'pr'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'trial_days' => 7, 'limits' => $limits, 'features' => $features]);
}

function prShop(array $attrs = []): Restaurant
{
    return Restaurant::create($attrs + ['name' => 'Olive Grove', 'slug' => 'pr'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now()]);
}

function prOwner(Restaurant $r): User
{
    $user = User::factory()->create(['restaurant_id' => $r->id]);
    $r->update(['owner_id' => $user->id]);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($r->id);
    $user->assignRole(Permissions::OWNER);
    $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return $user;
}

it('lets the platform admin add a restaurant with its owner and plan', function () {
    Mail::fake();
    $plan = prPlan();

    $this->actingAs($this->admin)->get(route('admin.restaurants.create'))->assertOk()->assertSee(__('platform.new_restaurant'));
    $this->actingAs($this->admin)->get(route('admin.restaurants.index'))->assertOk()->assertSee(route('admin.restaurants.create'));

    $this->actingAs($this->admin)->post(route('admin.restaurants.store'), [
        'restaurant_name' => 'Fig & Olive', 'owner_name' => 'Ola Owner', 'owner_email' => 'ola@example.com', 'locale' => 'en', 'currency_code' => 'USD',
        'plan_id' => $plan->id, 'start_trial' => 1, 'is_premium' => 1, 'is_featured' => 1, 'badge' => 'verified',
    ])->assertRedirect();

    $r = Restaurant::where('name', 'Fig & Olive')->firstOrFail();
    $owner = User::where('email', 'ola@example.com')->firstOrFail();
    expect($r->slug)->toBe('fig-olive')->and($r->owner_id)->toBe($owner->id)->and($owner->restaurant_id)->toBe($r->id)->and($owner->hasVerifiedEmail())->toBeTrue()
        ->and($r->is_premium)->toBeTrue()->and($r->is_featured)->toBeTrue()->and($r->badgeKey())->toBe('verified');

    app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
    expect($owner->fresh()->hasRole(Permissions::OWNER))->toBeTrue();
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));

    $sub = app(SubscriptionService::class)->current($r);
    expect($sub->plan_id)->toBe($plan->id)->and($sub->status)->toBe('trialing');
    Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'password_reset' && $m->hasTo('ola@example.com')); // no password typed: the owner is invited to choose one
});

it('uses the typed password and refuses duplicates and bad input', function () {
    Mail::fake();
    $this->actingAs($this->admin)->post(route('admin.restaurants.store'), ['restaurant_name' => 'Pw Place', 'owner_name' => 'P', 'owner_email' => 'p@example.com', 'password' => 'a-long-password', 'locale' => 'en'])->assertRedirect();
    Mail::assertNotSent(fn (TemplatedMail $m) => $m->templateKey === 'password_reset');
    expect(auth()->guard('web')->validate(['email' => 'p@example.com', 'password' => 'a-long-password']))->toBeTrue();

    $post = fn (array $over) => $this->actingAs($this->admin)->post(route('admin.restaurants.store'), $over + ['restaurant_name' => 'Dup', 'owner_name' => 'D', 'owner_email' => 'd@example.com', 'locale' => 'en']);
    $post(['owner_email' => 'p@example.com'])->assertSessionHasErrors('owner_email');
    $post(['slug' => 'Bad Slug'])->assertSessionHasErrors('slug');
    $post(['locale' => 'xx'])->assertSessionHasErrors('locale');
    $post(['badge' => 'galactic'])->assertSessionHasErrors('badge');
    $post(['plan_id' => 9999])->assertSessionHasErrors('plan_id');
    $post(['password' => 'short'])->assertSessionHasErrors('password');
});

it('keeps restaurant creation and options to platform admins', function () {
    $r = prShop();
    $owner = prOwner($r);

    $this->post(route('admin.restaurants.store'), [])->assertRedirect(route('login'));
    $this->actingAs($owner)->get(route('admin.restaurants.create'))->assertForbidden();
    $this->actingAs($owner)->post(route('admin.restaurants.store'), [])->assertForbidden();
    $this->actingAs($owner)->put(route('admin.restaurants.options', $r->id), ['is_premium' => 1])->assertForbidden();
    expect($r->fresh()->is_premium)->toBeFalse();
});

it('saves badge, featured, premium, custom limits and feature switches', function () {
    $plan = prPlan(['tables' => 5, 'products' => 50], ['analytics' => false, 'api' => true]);
    $r = prShop();
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $limits = app(LimitGuard::class);

    expect($limits->limit($r, 'tables'))->toBe(5)->and($limits->hasFeature($r, 'analytics'))->toBeFalse()->and($limits->hasFeature($r, 'api'))->toBeTrue();

    $this->actingAs($this->admin)->get(route('admin.restaurants.show', $r->id))->assertOk()->assertSee(__('platform.options_title'));
    $this->actingAs($this->admin)->put(route('admin.restaurants.options', $r->id), [
        'is_premium' => 1, 'is_featured' => 1, 'featured_order' => 3, 'badge' => 'partner',
        'is_limited' => 1, 'limits' => ['tables' => '12', 'products' => 'unlimited', 'staff' => ''],
        'features' => ['analytics' => 'on', 'api' => 'off', 'reservations' => ''],
        'admin_notes' => 'Friend of the family',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $r = $r->fresh();
    expect($r->badgeKey())->toBe('partner')->and($r->featured_order)->toBe(3)->and($r->limit_overrides)->toBe(['tables' => 12, 'products' => 'unlimited'])
        ->and($r->feature_overrides)->toBe(['analytics' => true, 'api' => false])->and($r->admin_notes)->toBe('Friend of the family');
    expect($limits->limit($r, 'tables'))->toBe(12)->and($limits->limit($r, 'products'))->toBeNull()->and($limits->limit($r, 'branches'))->toBe($plan->limit('branches'))
        ->and($limits->hasFeature($r, 'analytics'))->toBeTrue()->and($limits->hasFeature($r, 'api'))->toBeFalse()->and($limits->hasFeature($r, 'premium_themes'))->toBeTrue();

    // Switching "limited" off goes back to the plan; the typed numbers stay for later.
    $this->actingAs($this->admin)->put(route('admin.restaurants.options', $r->id), ['limits' => ['tables' => '12'], 'is_featured' => 1])->assertSessionHasNoErrors()->assertRedirect();
    expect($limits->limit($r->fresh(), 'tables'))->toBe(5)->and($r->fresh()->is_premium)->toBeFalse();
});

it('rejects invalid option values', function () {
    $r = prShop();
    $put = fn (array $d) => $this->actingAs($this->admin)->put(route('admin.restaurants.options', $r->id), $d);

    $put(['badge' => 'nope'])->assertSessionHasErrors('badge');
    $put(['limits' => ['tables' => '-3']])->assertSessionHasErrors('limits.tables');
    $put(['limits' => ['tables' => 'lots']])->assertSessionHasErrors('limits.tables');
    $put(['features' => ['api' => 'maybe']])->assertSessionHasErrors('features.api');
    $put(['theme' => 'does-not-exist'])->assertSessionHasErrors('theme');
    $put(['featured_order' => 99999])->assertSessionHasErrors('featured_order');
});

it('enforces a custom limit where the plan limit is checked', function () {
    $plan = prPlan(['tables' => 1]);
    $r = prShop();
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $owner = prOwner($r);

    $this->actingAs($owner)->post(route('tables.store'), ['name' => 'T1'])->assertSessionHasNoErrors()->assertRedirect();
    $this->actingAs($owner)->post(route('tables.store'), ['name' => 'T2'])->assertSessionHasErrors('limit');

    $r->update(['is_limited' => true, 'limit_overrides' => ['tables' => 3]]);
    $owner = User::find($owner->id); // a fresh request sees the changed restaurant
    $this->actingAs($owner)->post(route('tables.store'), ['name' => 'T2'])->assertSessionHasNoErrors()->assertRedirect();
});

it('shows the badge on the menu and lists featured restaurants publicly', function () {
    $featured = prShop(['name' => 'Showcase Bistro', 'is_featured' => true, 'is_premium' => true, 'featured_order' => 1]);
    $plain = prShop(['name' => 'Quiet Corner']);
    $second = prShop(['name' => 'Second Plate', 'is_featured' => true, 'badge' => 'new', 'featured_order' => 0]);
    $notOnboarded = prShop(['name' => 'Half Done', 'is_featured' => true, 'onboarded_at' => null]);
    $suspended = prShop(['name' => 'Closed Down', 'is_featured' => true, 'status' => 'suspended']);

    foreach ([$featured, $plain] as $shop) {
        app(SubscriptionService::class)->assign($shop, prPlan(), now()->addMonth());
    }

    $this->get('/r/'.$featured->slug)->assertOk()->assertSee(__('platform.badge_premium'));
    $this->get('/r/'.$plain->slug)->assertOk()->assertDontSee(__('platform.badge_premium'));

    $html = $this->get('/restaurants')->assertOk()->assertSee(__('platform.directory_title'))->getContent();
    expect($html)->toContain('Showcase Bistro')->toContain('Second Plate')->not->toContain('Quiet Corner')->not->toContain('Half Done')->not->toContain('Closed Down');
    expect(strpos($html, 'Showcase Bistro'))->toBeLessThan(strpos($html, 'Second Plate')); // premium first

    $this->get('/')->assertOk()->assertSee('Showcase Bistro')->assertSee(route('directory'));
    expect($this->get('/sitemap.xml')->getContent())->toContain('/restaurants');

    // The platform owner can close the directory.
    app(SettingsService::class)->set('general.directory_enabled', '0');
    $this->get('/restaurants')->assertNotFound();
    $this->get('/')->assertOk()->assertDontSee('Showcase Bistro');
});

it('lets the platform admin pin a theme the owner cannot change', function () {
    $r = prShop(['theme' => 'classic']);
    $owner = prOwner($r);
    app(SubscriptionService::class)->assign($r, prPlan(), now()->addMonth());

    $this->actingAs($this->admin)->put(route('admin.restaurants.options', $r->id), ['theme' => 'modern', 'theme_locked' => 1])->assertSessionHasNoErrors()->assertRedirect();
    expect($r->fresh()->theme)->toBe('modern')->and($r->fresh()->theme_locked)->toBeTrue();

    $this->actingAs($owner)->get(route('appearance.edit'))->assertOk()->assertSee(__('platform.theme_locked_notice'));
    $this->actingAs($owner)->put(route('appearance.update'), ['theme' => 'classic', 'font' => 'sans', 'layout' => 'list', 'radius' => 'soft', 'hero' => 'full', 'scroll' => 'all', 'scrollbar' => 'default', 'reveal' => 'none', 'card' => 'flat', 'decor' => 'none', 'button' => 'solid', 'heading' => 'plain', 'hover' => 'none'])->assertSessionHasErrors('theme');
    expect($r->fresh()->theme)->toBe('modern');

    $this->actingAs($this->admin)->put(route('admin.restaurants.options', $r->id), ['theme' => 'modern', 'theme_locked' => 0])->assertSessionHasNoErrors()->assertRedirect();
    $owner = User::find($owner->id);
    $this->actingAs($owner)->put(route('appearance.update'), ['theme' => 'classic', 'font' => 'sans', 'layout' => 'list', 'radius' => 'soft', 'hero' => 'full', 'scroll' => 'all', 'scrollbar' => 'default', 'reveal' => 'none', 'card' => 'flat', 'decor' => 'none', 'button' => 'solid', 'heading' => 'plain', 'hover' => 'none'])->assertSessionHasNoErrors()->assertRedirect();
    expect($r->fresh()->theme)->toBe('classic');
});

it('filters the restaurant list by flag and shows the flags', function () {
    prShop(['name' => 'Flagged One', 'is_featured' => true, 'badge' => 'verified']);
    prShop(['name' => 'Plain Two']);
    prShop(['name' => 'Limited Three', 'is_limited' => true]);

    $this->actingAs($this->admin)->get(route('admin.restaurants.index', ['flag' => 'featured']))->assertOk()->assertSee('Flagged One')->assertDontSee('Plain Two')->assertDontSee('Limited Three')->assertSee(__('platform.badge_verified'));
    $this->actingAs($this->admin)->get(route('admin.restaurants.index', ['flag' => 'limited']))->assertOk()->assertSee('Limited Three')->assertDontSee('Flagged One');
});
