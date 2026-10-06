<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Demo\Support\DemoDataRegistry;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

afterEach(fn () => DemoDataRegistry::flush());

it('blocks state-changing requests in demo mode but not reads or allowlisted routes', function () {
    config(['demo.enabled' => true]);
    $user = User::factory()->create(['password' => 'demo-password']);

    $this->actingAs($user)->post('/profile/two-factor', ['code' => '1'])->assertSessionHasErrors('demo');
    $this->actingAs($user)->delete('/profile/two-factor', ['password' => 'x'])->assertSessionHasErrors('demo');
    $this->actingAs($user)->get('/profile/two-factor')->assertOk();

    auth()->logout();
    $this->post('/login', ['email' => $user->email, 'password' => 'demo-password'])->assertRedirect(route('dashboard'));
});

it('answers json requests with 403 in demo mode', function () {
    config(['demo.enabled' => true]);

    $this->actingAs(User::factory()->create())
        ->postJson('/profile/two-factor', [])->assertForbidden()->assertJsonPath('message', __('demo.blocked'));
});

it('does nothing special when demo mode is off', function () {
    config(['demo.enabled' => false]);

    $this->actingAs(User::factory()->create())->post('/profile/two-factor', ['code' => '1'])
        ->assertSessionHasErrors('code')->assertSessionDoesntHaveErrors('demo');
});

it('shows the demo banner and accounts on the login page only in demo mode', function () {
    $this->get('/login')->assertDontSee(__('demo.banner'));

    config(['demo.enabled' => true]);
    $this->get('/login')->assertSee(__('demo.banner'))->assertSee('admin@demo.test');
});

it('seeds demo restaurants with a full staff team and runs registered module seeders per tenant', function () {
    $ran = [];
    $probe = new class extends Seeder
    {
        public static array $ran = [];

        public function run(): void
        {
            self::$ran[] = app(TenantContext::class)->get()->slug;
        }
    };
    DemoDataRegistry::register($probe::class);

    $this->seed(DemoSeeder::class);

    expect(Restaurant::count())->toBe(count(DemoSeeder::RESTAURANTS))
        ->and($probe::$ran)->toHaveCount(3)->toContain('bella-italia');

    $owner = User::where('email', 'owner@bella-italia.demo')->firstOrFail();
    app(PermissionRegistrar::class)->setPermissionsTeamId($owner->restaurant_id);
    expect($owner->hasRole(Permissions::OWNER))->toBeTrue()
        ->and($owner->restaurant->owner_id)->toBe($owner->id)
        ->and(User::where('restaurant_id', $owner->restaurant_id)->count())->toBe(5);

    // Idempotent: running it again must not duplicate anything.
    $this->seed(DemoSeeder::class);
    expect(Restaurant::count())->toBe(3)->and(User::count())->toBe(16);
});

it('refuses demo:reset outside demo mode so a real install can never be wiped', function () {
    config(['demo.enabled' => false]);
    User::factory()->create();

    $this->artisan('demo:reset')->assertFailed();
    expect(User::count())->toBe(1);
});
