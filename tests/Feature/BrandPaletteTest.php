<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Core\Support\BrandPalette;
use Database\Seeders\LanguagesAndCurrenciesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

it('builds ten shades that run from light to dark around the chosen colour', function () {
    $shades = BrandPalette::shades('#2a78d6');

    expect($shades)->toHaveCount(10)->and($shades[500])->toBe('#2a78d6');
    foreach ([50, 100, 200, 300, 400] as $light) {
        expect(BrandPalette::contrast($shades[$light], '#ffffff'))->toBeLessThan(BrandPalette::contrast($shades[500], '#ffffff'));
    }
    expect(BrandPalette::contrast($shades[900], '#ffffff'))->toBeGreaterThan(BrandPalette::contrast($shades[500], '#ffffff'));
});

it('picks readable text for any primary colour', function (string $hex) {
    expect(BrandPalette::contrast($hex, BrandPalette::onColor($hex)))->toBeGreaterThanOrEqual(4.0);
})->with(['#ffb020', '#ffffff', '#000000', '#c0392b', '#2a78d6', '#7b2d8e', '#f5e663', '#1c8e73']);

it('keeps link colours readable on both backgrounds, whatever the accent', function (string $hex) {
    expect(BrandPalette::contrast(BrandPalette::readable($hex, '#ffffff'), '#ffffff'))->toBeGreaterThanOrEqual(4.5)
        ->and(BrandPalette::contrast(BrandPalette::readable($hex, '#14171d'), '#14171d'))->toBeGreaterThanOrEqual(4.5);
})->with(['#ffe066', '#ffffff', '#000000', '#1c8e73', '#ff5a5f', '#9bd1ff']);

it('prints nothing for the default palette and only safe hex values otherwise', function () {
    expect(BrandPalette::css(null, null))->toBe('')->and(BrandPalette::css('#ffb020', '#1c8e73'))->toBe('');

    $css = BrandPalette::css('#2a78d6', '#c0392b');
    expect($css)->toContain('--color-brand-500:#2a78d6;')->toContain('--color-accent-500:#c0392b;')->toContain('--on-brand:')->toContain(':root.dark{--link:')
        ->and(preg_match('/[^#a-z0-9:;{}.\-,\s]/', $css))->toBe(0);

    // Garbage never reaches the stylesheet: it falls back to the defaults.
    expect(BrandPalette::css('red;}body{display:none', '</style><script>'))->toBe('');
});

it('lets the platform admin change the colours and applies them site wide', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(LanguagesAndCurrenciesSeeder::class);
    $admin = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $admin->assignRole(Permissions::SUPER_ADMIN);

    $this->get('/')->assertDontSee('brand-palette', false);

    $this->actingAs($admin)->put(route('admin.settings.section.update', 'general'), [
        'site_name' => 'My Menu', 'default_language' => 'en', 'default_currency' => 'USD', 'timezone' => 'UTC',
        'brand_color' => '#2a78d6', 'accent_color' => '#c0392b',
    ])->assertRedirect();

    expect(app(SettingsService::class)->get('general.brand_color'))->toBe('#2a78d6');
    foreach (['/', '/blog', '/admin'] as $url) {
        $this->get($url)->assertSee('id="brand-palette"', false)->assertSee('--color-brand-500:#2a78d6', false);
    }

    $this->put(route('admin.settings.section.update', 'general'), ['site_name' => 'My Menu', 'default_language' => 'en', 'default_currency' => 'USD', 'timezone' => 'UTC', 'brand_color' => 'blue'])
        ->assertSessionHasErrors('brand_color');
    $this->put(route('admin.settings.section.update', 'general'), ['site_name' => 'My Menu', 'default_language' => 'en', 'default_currency' => 'USD', 'timezone' => 'UTC'])->assertRedirect();
    $this->get('/')->assertDontSee('brand-palette', false); // cleared fields return to the default palette
});
