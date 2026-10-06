<?php

use App\Models\User;
use App\Modules\Auth\Services\RestaurantRoleService;
use App\Modules\Auth\Services\TwoFactorService;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function registerPayload(array $overrides = []): array
{
    return array_merge([
        'restaurant_name' => 'Burger Barn',
        'name' => 'Ada Owner',
        'email' => 'ada@example.com',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
    ], $overrides);
}

it('registers a restaurant together with its owner', function () {
    Mail::fake();

    $this->post('/register', registerPayload())->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'ada@example.com')->firstOrFail();
    $restaurant = Restaurant::where('slug', 'burger-barn')->firstOrFail();

    expect($user->restaurant_id)->toBe($restaurant->id)
        ->and($restaurant->owner_id)->toBe($user->id);

    app(PermissionRegistrar::class)->setPermissionsTeamId($restaurant->id);
    expect($user->fresh()->hasRole(Permissions::OWNER))->toBeTrue();

    Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'verify_email' && $m->hasTo('ada@example.com'));
    Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'welcome');
});

it('generates unique slugs for restaurants with the same name', function () {
    $this->post('/register', registerPayload())->assertRedirect();
    auth()->logout();
    $this->post('/register', registerPayload(['email' => 'bob@example.com']))->assertRedirect();

    expect(Restaurant::pluck('slug')->unique())->toHaveCount(2);
});

it('can disable public registration from settings', function () {
    app(SettingsService::class)->set('auth.registration_enabled', '0');

    $this->get('/register')->assertNotFound();
});

it('logs a user in and rejects bad credentials', function () {
    $user = User::factory()->create(['password' => 'correct-horse']);

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('blocks login for users of a suspended restaurant', function () {
    $restaurant = Restaurant::create(['name' => 'Closed', 'slug' => 'closed', 'status' => Restaurant::STATUS_SUSPENDED]);
    $user = User::factory()->create(['restaurant_id' => $restaurant->id, 'password' => 'correct-horse']);

    $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('requires a second factor when two-factor is enabled', function () {
    $service = app(TwoFactorService::class);
    $user = User::factory()->create(['password' => 'correct-horse']);
    $secret = $service->generateSecret();
    $codes = $service->enable($user, $secret);

    $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse'])
        ->assertRedirect(route('two-factor.challenge'));
    $this->assertGuest();

    $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
    $this->assertGuest();

    // A recovery code logs in and is then consumed.
    $this->post('/two-factor-challenge', ['code' => $codes[0]])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->two_factor_recovery_codes)->not->toContain($codes[0]);
});

it('keeps the google login hidden until the admin enables it', function () {
    $this->get('/auth/google')->assertNotFound();
});

it('scopes permission checks to the user\'s restaurant', function () {
    $a = Restaurant::create(['name' => 'A', 'slug' => 'a']);
    $b = Restaurant::create(['name' => 'B', 'slug' => 'b']);
    $user = User::factory()->create(['restaurant_id' => $a->id]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($a->id);
    $user->assignRole(Permissions::MANAGER);

    app(PermissionRegistrar::class)->setPermissionsTeamId($a->id);
    expect($user->fresh()->hasRole(Permissions::MANAGER))->toBeTrue();

    app(PermissionRegistrar::class)->setPermissionsTeamId($b->id);
    expect($user->fresh()->hasRole(Permissions::MANAGER))->toBeFalse();
});

it('lets an owner create a restaurant-only custom role', function () {
    $a = Restaurant::create(['name' => 'A', 'slug' => 'a']);
    $b = Restaurant::create(['name' => 'B', 'slug' => 'b']);
    $service = app(RestaurantRoleService::class);

    $role = $service->create($a, 'Barista', ['orders.view', 'platform.manage', 'nonsense']);

    // Platform and unknown permissions are silently dropped.
    expect($role->permissions->pluck('name')->all())->toBe(['orders.view'])
        ->and($role->restaurant_id)->toBe($a->id);

    // Another restaurant can reuse the name; this one cannot.
    expect($service->create($b, 'Barista', [])->restaurant_id)->toBe($b->id);
    expect(fn () => $service->create($a, 'Barista', []))->toThrow(ValidationException::class);
});

it('reserves the built-in role names', function () {
    $a = Restaurant::create(['name' => 'A', 'slug' => 'a']);

    expect(fn () => app(RestaurantRoleService::class)->create($a, Permissions::SUPER_ADMIN, []))
        ->toThrow(ValidationException::class);
});

it('creates a super admin from the command line', function () {
    $this->artisan('app:create-super-admin', ['email' => 'root@example.com', '--password' => 'long-enough-1'])->assertSuccessful();

    $user = User::where('email', 'root@example.com')->firstOrFail();
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));

    expect($user->isPlatformUser())->toBeTrue()
        ->and($user->hasRole(Permissions::SUPER_ADMIN))->toBeTrue();
});
