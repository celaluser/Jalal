<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Models\Translation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Language::firstOrCreate(['code' => 'tr'], ['name' => 'Turkish', 'is_active' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true, 'is_default' => true, 'decimals' => 2, 'decimal_separator' => '.', 'thousands_separator' => ',']);
    $this->admin = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $this->admin->assignRole(Permissions::SUPER_ADMIN);
});

it('adds a language and keeps the default one on', function () {
    $this->actingAs($this->admin)->post(route('admin.languages.store'), ['code' => 'ar-SA', 'name' => 'Arabic', 'is_rtl' => '1'])->assertSessionHasNoErrors();
    expect(Language::where('code', 'ar-SA')->first())->is_rtl->toBeTrue();
    $this->actingAs($this->admin)->post(route('admin.languages.store'), ['code' => 'ar-SA', 'name' => 'Dup'])->assertSessionHasErrors('code');
    $this->actingAs($this->admin)->post(route('admin.languages.store'), ['code' => 'not valid', 'name' => 'X'])->assertSessionHasErrors('code');

    $en = Language::where('code', 'en')->first();
    $this->actingAs($this->admin)->put(route('admin.languages.update', $en), ['name' => 'English', 'is_active' => null]);
    expect($en->fresh()->is_active)->toBeTrue(); // the default cannot be switched off

    $tr = Language::where('code', 'tr')->first();
    $this->actingAs($this->admin)->post(route('admin.languages.default', $tr));
    expect($tr->fresh()->is_default)->toBeTrue()->and($en->fresh()->is_default)->toBeFalse();
});

it('adds and edits currencies and shows how a number will look', function () {
    $this->actingAs($this->admin)->post(route('admin.currencies.store'), ['code' => 'chf', 'name' => 'Swiss franc', 'symbol' => 'CHF', 'symbol_position' => 'after', 'decimals' => 2, 'decimal_separator' => ',', 'thousands_separator' => '.'])->assertSessionHasNoErrors();
    $chf = Currency::where('code', 'CHF')->first();
    expect($chf->format(1234567.891))->toBe('1.234.567,89 CHF');

    $this->actingAs($this->admin)->put(route('admin.currencies.update', $chf), ['name' => 'Franc', 'symbol' => 'Fr.', 'symbol_position' => 'before', 'decimals' => 0, 'decimal_separator' => '.', 'thousands_separator' => "'"])->assertSessionHasNoErrors();
    expect($chf->fresh()->format(1234.5))->toBe("Fr.1'235");
    $this->actingAs($this->admin)->get(route('admin.currencies.index'))->assertOk()->assertSee('CHF');
    $this->actingAs($this->admin)->post(route('admin.currencies.store'), ['code' => 'USD', 'name' => 'Dup', 'symbol' => '$', 'symbol_position' => 'before', 'decimals' => 2, 'decimal_separator' => '.'])->assertSessionHasErrors('code');
});

it('overrides a text without touching files, and removes the override when it matches the file again', function () {
    $this->actingAs($this->admin)->get(route('admin.translations.index', ['locale' => 'tr', 'group' => 'customer']))->assertOk()->assertSee('sold_out');
    $this->actingAs($this->admin)->put(route('admin.translations.update'), ['locale' => 'tr', 'group' => 'customer', 'rows' => ['sold_out' => 'Bitti!']])->assertSessionHasNoErrors();
    expect(Translation::where(['locale' => 'tr', 'group' => 'customer', 'key' => 'sold_out'])->value('value'))->toBe('Bitti!')->and(trans('customer.sold_out', [], 'tr'))->toBe('Bitti!');

    $this->actingAs($this->admin)->put(route('admin.translations.update'), ['locale' => 'tr', 'group' => 'customer', 'rows' => ['sold_out' => 'Tükendi']]);
    expect(Translation::where('key', 'sold_out')->exists())->toBeFalse();

    $this->actingAs($this->admin)->put(route('admin.translations.update'), ['locale' => 'tr', 'group' => 'customer', 'rows' => ['not_a_real_key' => 'x']]);
    expect(Translation::where('key', 'not_a_real_key')->exists())->toBeFalse();
});

it('searches, resets, and rejects unknown files', function () {
    Translation::create(['locale' => 'tr', 'group' => 'customer', 'key' => 'add', 'value' => 'Koy']);
    $this->actingAs($this->admin)->get(route('admin.translations.index', ['locale' => 'tr', 'group' => 'customer', 'q' => 'Sold out']))->assertOk()->assertSee('sold_out')->assertDontSee('hero_title');
    $this->actingAs($this->admin)->delete(route('admin.translations.reset'), ['locale' => 'tr', 'group' => 'customer'])->assertRedirect();
    expect(Translation::count())->toBe(0);
    $this->actingAs($this->admin)->get(route('admin.translations.index', ['group' => '../../.env']))->assertOk()->assertDontSee('APP_KEY');
});

it('is for super admins only', function () {
    $u = User::factory()->create();
    foreach (['admin.languages.index', 'admin.currencies.index', 'admin.translations.index'] as $r) {
        $this->actingAs($u)->get(route($r))->assertForbidden();
    }
});
