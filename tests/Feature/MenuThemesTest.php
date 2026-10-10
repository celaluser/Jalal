<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Menu\Services\ThemeLibrary;
use App\Modules\Menu\Services\ThemeRegistry;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
});

function mtAdmin(): User
{
    $admin = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $admin->assignRole(Permissions::SUPER_ADMIN);

    return $admin;
}

function mtShop(string $theme = 'aurora', array $menu = []): array
{
    $r = Restaurant::create(['name' => 'Theme Bistro', 'slug' => 'tb'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now(), 'theme' => $theme, 'branding' => ['menu' => $menu]]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => [], 'features' => []]), now()->addMonth());
    $owner = User::factory()->create(['restaurant_id' => $r->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
    $owner->assignRole(Permissions::OWNER);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return [$r, $owner];
}

function mtData(array $over = []): array
{
    return $over + ['name' => 'Custom', 'dark' => '1', 'bg' => '#101820', 'bg2' => '#203040', 'surface' => '#182430', 'fg' => '#f0f0f0', 'muted' => '#aab0b8', 'line' => '#2c3a48', 'font' => 'display', 'layout' => 'cards', 'radius' => 'round',
        'bg_style' => 'gradient', 'card' => 'glass', 'scrollbar' => 'glow', 'reveal' => 'rise', 'decor' => 'orbs', 'button' => 'gradient', 'heading' => 'glow', 'hover' => 'lift', 'progress' => '1'];
}

it('ships three dynamic themes with their own motion settings', function () {
    $themes = app(ThemeLibrary::class)->enabled();
    expect($themes)->toHaveKeys(['aurora', 'neon', 'sunset']);
    expect([$themes['aurora']['scrollbar'], $themes['neon']['scrollbar'], $themes['sunset']['scrollbar']])->toBe(['gradient', 'glow', 'pill']);
});

it('renders the animated scroll rail, progress bar and reveal for a dynamic theme, and none for a plain one', function () {
    [$r] = mtShop('aurora');
    $html = $this->get('/r/'.$r->slug)->assertOk()->getContent();
    expect($html)->toContain('class="menu-rail"')->toContain('class="menu-progress"')->toContain('animation-timeline: view()')->toContain('backdrop-filter');

    [$plain] = mtShop('classic');
    $html = $this->get('/r/'.$plain->slug)->assertOk()->getContent();
    expect($html)->not->toContain('class="menu-rail"')->not->toContain('class="menu-progress"');
});

it('lets a restaurant override the effects of its theme', function () {
    [$r, $owner] = mtShop('aurora');
    $this->actingAs($owner)->put(route('appearance.update'), ['theme' => 'aurora', 'font' => 'sans', 'layout' => 'cards', 'radius' => 'round', 'scrollbar' => 'slim', 'reveal' => 'none', 'card' => 'flat', 'progress' => 'off', 'animated_bg' => 'off'])->assertSessionHasNoErrors();
    $s = app(ThemeRegistry::class)->settings($r->fresh());
    expect([$s['scrollbar'], $s['reveal'], $s['card'], $s['progress'], $s['animated_bg']])->toBe(['slim', 'none', 'flat', false, false]);

    $html = $this->get('/r/'.$r->slug)->assertOk()->getContent();
    expect($html)->not->toContain('class="menu-rail"')->not->toContain('class="menu-progress"');

    $this->actingAs($owner)->put(route('appearance.update'), ['theme' => 'aurora', 'font' => 'sans', 'layout' => 'cards', 'radius' => 'round', 'scrollbar' => 'rainbow'])->assertSessionHasErrors('scrollbar');
});

it('lets the super admin edit a built-in theme, restore it, and keep restaurants away from the studio', function () {
    $admin = mtAdmin();
    $this->actingAs($admin)->get(route('admin.themes.index'))->assertOk()->assertSee('Aurora')->assertSee('Neon')->assertSee('Sunset');
    $this->actingAs($admin)->get(route('admin.themes.edit', 'neon'))->assertOk();

    $this->actingAs($admin)->put(route('admin.themes.update', 'neon'), mtData(['name' => 'Neon Pro', 'bg' => '#000000']))->assertRedirect(route('admin.themes.index'));
    $neon = app(ThemeLibrary::class)->all()['neon'];
    expect($neon['name'])->toBe('Neon Pro')->and($neon['bg'])->toBe('#000000')->and($neon['builtin'])->toBeTrue();

    $this->actingAs($admin)->delete(route('admin.themes.destroy', 'neon'))->assertRedirect();
    expect(app(ThemeLibrary::class)->all()['neon']['name'])->toBe('Neon')->and(app(ThemeLibrary::class)->all()['neon']['bg'])->toBe('#050507');

    [, $owner] = mtShop();
    $this->actingAs($owner)->get(route('admin.themes.index'))->assertForbidden();
});

it('creates, uses, switches off and deletes a custom theme', function () {
    $admin = mtAdmin();
    $this->actingAs($admin)->post(route('admin.themes.store'), mtData(['name' => '<b>Harbor</b>']))->assertRedirect(route('admin.themes.index'));
    $custom = collect(app(ThemeLibrary::class)->all())->first(fn ($t) => ! $t['builtin']);
    expect($custom['name'])->toBe('Harbor')->and($custom['scrollbar'])->toBe('glow');

    [$r, $owner] = mtShop($custom['key']);
    $html = $this->get('/r/'.$r->slug)->assertOk()->getContent();
    expect($html)->toContain('--menu-bg:#101820')->toContain('class="menu-rail"');
    $this->actingAs($owner)->get(route('appearance.edit'))->assertOk()->assertSee('Harbor');

    // Switched off: restaurants on it fall back to the default theme, and it can no longer be chosen.
    $this->actingAs($admin)->post(route('admin.themes.toggle', $custom['key']))->assertRedirect();
    expect(app(ThemeRegistry::class)->settings($r->fresh())['theme'])->toBe('classic');
    $this->actingAs($owner)->put(route('appearance.update'), ['theme' => $custom['key'], 'font' => 'sans', 'layout' => 'list', 'radius' => 'soft'])->assertSessionHasErrors('theme');

    $this->actingAs($admin)->delete(route('admin.themes.destroy', $custom['key']))->assertRedirect();
    expect(app(ThemeLibrary::class)->all())->not->toHaveKey($custom['key']);
});

it('rejects bad colours and unknown styles, and always keeps one theme on', function () {
    $admin = mtAdmin();
    $this->actingAs($admin)->post(route('admin.themes.store'), mtData(['bg' => 'red; } body { display:none', 'scrollbar' => 'rainbow']))->assertSessionHasErrors(['bg', 'scrollbar']);

    foreach (array_keys(app(ThemeLibrary::class)->all()) as $key) {
        $this->actingAs($admin)->post(route('admin.themes.toggle', $key));
    }

    expect(app(ThemeLibrary::class)->enabled())->not->toBeEmpty();
    $this->actingAs($admin)->post(route('admin.themes.default', 'sunset'))->assertRedirect();
    expect(app(ThemeLibrary::class)->defaultKey())->toBe('sunset');
});

it('draws the decoration, gradient buttons and card hover of a theme, and lets a restaurant turn them off', function () {
    [$r, $owner] = mtShop('neon');
    $html = $this->get('/r/'.$r->slug)->assertOk()->getContent();
    expect($html)->toContain('background-size:44px 44px')->toContain('.menu-card:hover{box-shadow')->toContain('text-shadow:0 0 14px');

    $this->actingAs($owner)->put(route('appearance.update'), ['theme' => 'neon', 'font' => 'sans', 'layout' => 'cards', 'radius' => 'soft', 'decor' => 'none', 'button' => 'solid', 'heading' => 'plain', 'hover' => 'none'])->assertSessionHasNoErrors();
    $html = $this->get('/r/'.$r->slug)->assertOk()->getContent();
    expect($html)->not->toContain('background-size:44px 44px')->not->toContain('.menu-card:hover{box-shadow')->not->toContain('text-shadow:0 0 14px');

    $this->actingAs($owner)->put(route('appearance.update'), ['theme' => 'neon', 'font' => 'sans', 'layout' => 'cards', 'radius' => 'soft', 'decor' => 'fireworks'])->assertSessionHasErrors('decor');
});
