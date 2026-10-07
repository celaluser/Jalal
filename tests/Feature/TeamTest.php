<?php

use App\Models\User;
use App\Modules\Auth\Services\RestaurantRoleService;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Billing\Support\UsageRegistry;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Models\Language;
use App\Modules\Team\Services\TeamService;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
});

/** A restaurant with an owner; $staffLimit null = unlimited. */
function tmShop(?int $staffLimit = null, string $slug = 'tm'): array
{
    $r = Restaurant::create(['name' => 'Team '.$slug, 'slug' => $slug.uniqid(), 'locale' => 'en', 'currency_code' => 'USD', 'onboarded_at' => now()]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => ['staff' => $staffLimit], 'features' => []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $owner = tmUser($r, Permissions::OWNER);
    $r->forceFill(['owner_id' => $owner->id])->save();

    return [$r->fresh(), $owner];
}

function tmUser(Restaurant $r, string $role): User
{
    $user = User::factory()->create(['restaurant_id' => $r->id]);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($r->id);
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return $user;
}

function tmRoleOf(User $user): ?string
{
    return app(TeamService::class)->roleOf($user->fresh());
}

describe('inviting', function () {
    it('invites a person, assigns the role and mails a set-password link', function () {
        Mail::fake();
        [$r, $owner] = tmShop();

        $this->actingAs($owner)->post(route('team.store'), ['name' => 'Wendy', 'email' => 'Wendy@Example.com', 'role' => 'waiter'])
            ->assertRedirect(route('team.index'));

        $wendy = User::where('email', 'wendy@example.com')->firstOrFail();
        expect($wendy->restaurant_id)->toBe($r->id)->and($wendy->invited_at)->not->toBeNull()->and($wendy->hasVerifiedEmail())->toBeTrue()
            ->and(tmRoleOf($wendy))->toBe('waiter');
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'staff_invite' && $m->hasTo('wendy@example.com'));
    });

    it('lets the invited person choose a password through the link', function () {
        Mail::fake();
        [, $owner] = tmShop();
        $this->actingAs($owner)->post(route('team.store'), ['name' => 'Kim', 'email' => 'kim@example.com', 'role' => 'kitchen']);
        $kim = User::where('email', 'kim@example.com')->firstOrFail();

        $token = Password::broker()->createToken($kim);
        auth()->logout();
        $this->post(route('password.update'), ['token' => $token, 'email' => 'kim@example.com', 'password' => 'a-strong-pass-123', 'password_confirmation' => 'a-strong-pass-123'])->assertSessionHasNoErrors();

        expect(Hash::check('a-strong-pass-123', $kim->fresh()->password))->toBeTrue();
    });

    it('rejects duplicate e-mails and unknown roles', function () {
        [, $owner] = tmShop();
        $this->actingAs($owner)->post(route('team.store'), ['name' => 'X', 'email' => $owner->email, 'role' => 'waiter'])->assertSessionHasErrors('email');
        $this->actingAs($owner)->post(route('team.store'), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'owner'])->assertSessionHasErrors('role');
        $this->actingAs($owner)->post(route('team.store'), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'super_admin'])->assertSessionHasErrors('role');
    });

    it('enforces the plan staff limit', function () {
        Mail::fake();
        [, $owner] = tmShop(2); // the owner already counts as one
        $this->actingAs($owner)->post(route('team.store'), ['name' => 'A', 'email' => 'a@example.com', 'role' => 'waiter'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('team.store'), ['name' => 'B', 'email' => 'b@example.com', 'role' => 'waiter'])->assertSessionHasErrors('team');

        expect(User::where('email', 'b@example.com')->exists())->toBeFalse();
    });

    it('reports the staff counter to the usage registry', function () {
        [$r] = tmShop();
        tmUser($r, 'waiter');
        expect(UsageRegistry::count('staff', $r))->toBe(2);
    });

    it('can resend an invitation', function () {
        Mail::fake();
        [$r, $owner] = tmShop();
        $w = tmUser($r, 'waiter');
        $w->forceFill(['invited_at' => now()])->save();
        $this->actingAs($owner)->post(route('team.resend', $w))->assertRedirect();
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'staff_invite' && $m->hasTo($w->email));
    });
});

describe('managing people', function () {
    it('changes a role', function () {
        [$r, $owner] = tmShop();
        $w = tmUser($r, 'waiter');
        $this->actingAs($owner)->put(route('team.update', $w), ['role' => 'manager'])->assertRedirect(route('team.index'));
        expect(tmRoleOf($w))->toBe('manager');
    });

    it('protects the owner and the acting user', function () {
        [$r, $owner] = tmShop();
        // A custom role that may manage staff, so the protections (not the permission gate) are what stops these calls.
        app(RestaurantRoleService::class)->create($r, 'Lead', ['staff.manage']);
        $manager = tmUser($r, 'Lead');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($manager)->put(route('team.update', $owner), ['role' => 'waiter'])->assertSessionHasErrors('team');
        $this->actingAs($manager)->post(route('team.toggle', $owner))->assertSessionHasErrors('team');
        $this->actingAs($manager)->delete(route('team.destroy', $owner))->assertSessionHasErrors('team');
        $this->actingAs($manager)->put(route('team.update', $manager), ['role' => 'waiter'])->assertSessionHasErrors('team');
        $this->actingAs($manager)->delete(route('team.destroy', $manager))->assertSessionHasErrors('team');

        expect(User::find($owner->id))->not->toBeNull()->and(tmRoleOf($owner))->toBe(Permissions::OWNER);
    });

    it('switches a person off, ends their sessions and blocks sign-in until switched on', function () {
        [$r, $owner] = tmShop();
        $w = tmUser($r, 'waiter');
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $w->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($owner)->post(route('team.toggle', $w))->assertRedirect();
        expect($w->fresh()->disabled_at)->not->toBeNull()->and(DB::table('sessions')->where('user_id', $w->id)->exists())->toBeFalse();

        auth()->logout();
        $this->post(route('login'), ['email' => $w->email, 'password' => 'password'])->assertSessionHasErrors();
        $this->assertGuest();

        // A session that was already open is refused too.
        $this->actingAs($w->fresh())->get(route('orders.board'))->assertForbidden();

        $this->actingAs($owner)->post(route('team.toggle', $w))->assertRedirect();
        expect($w->fresh()->disabled_at)->toBeNull();
    });

    it('removes a person together with their role', function () {
        [$r, $owner] = tmShop();
        $w = tmUser($r, 'waiter');
        $this->actingAs($owner)->delete(route('team.destroy', $w))->assertRedirect(route('team.index'));
        expect(User::find($w->id))->toBeNull();
    });

    it('never reaches people of another restaurant', function () {
        [, $owner] = tmShop(null, 'a');
        [$other] = tmShop(null, 'b');
        $stranger = tmUser($other, 'waiter');

        $this->actingAs($owner)->get(route('team.edit', $stranger))->assertNotFound();
        $this->actingAs($owner)->put(route('team.update', $stranger), ['role' => 'manager'])->assertNotFound();
        $this->actingAs($owner)->post(route('team.toggle', $stranger))->assertNotFound();
        $this->actingAs($owner)->delete(route('team.destroy', $stranger))->assertNotFound();
        expect(User::find($stranger->id)->disabled_at)->toBeNull();
    });
});

describe('access', function () {
    it('shows the team list to the owner and to staff who may only view', function () {
        [$r, $owner] = tmShop();
        $this->actingAs($owner)->get(route('team.index'))->assertOk()->assertSee($owner->email);
        $this->actingAs($owner)->get(route('team.create'))->assertOk();
        $this->actingAs($owner)->get(route('team.roles.index'))->assertOk();
    });

    it('keeps waiters and kitchen staff out', function () {
        [$r] = tmShop();
        foreach (['waiter', 'kitchen'] as $role) {
            $u = tmUser($r, $role);
            $this->actingAs($u)->get(route('team.create'))->assertForbidden();
            $this->actingAs($u)->post(route('team.store'), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'waiter'])->assertForbidden();
            $this->actingAs($u)->get(route('team.roles.index'))->assertForbidden();
        }
    });

    it('requires a signed-in user', function () {
        $this->get(route('team.index'))->assertRedirect(route('login'));
    });
});

describe('custom roles', function () {
    it('creates a custom role that can then be invited', function () {
        Mail::fake();
        [$r, $owner] = tmShop();
        $this->actingAs($owner)->post(route('team.roles.store'), ['name' => 'Host', 'permissions' => ['orders.view', 'tables.view']])->assertRedirect(route('team.roles.index'));

        $role = Role::where('restaurant_id', $r->id)->where('name', 'Host')->firstOrFail();
        expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['orders.view', 'tables.view']);

        $this->actingAs($owner)->post(route('team.store'), ['name' => 'Hanna', 'email' => 'hanna@example.com', 'role' => 'Host'])->assertSessionHasNoErrors();
        expect(tmRoleOf(User::where('email', 'hanna@example.com')->first()))->toBe('Host');
    });

    it('ignores permissions that are not restaurant permissions', function () {
        [, $owner] = tmShop();
        $this->actingAs($owner)->post(route('team.roles.store'), ['name' => 'Evil', 'permissions' => ['admin.settings']])->assertSessionHasErrors('permissions.0');
    });

    it('updates permissions of its own role only', function () {
        [$r, $owner] = tmShop(null, 'a');
        [$other, $otherOwner] = tmShop(null, 'b');
        $this->actingAs($owner)->post(route('team.roles.store'), ['name' => 'Host', 'permissions' => ['orders.view']]);
        $mine = Role::where('restaurant_id', $r->id)->first();
        $this->actingAs($otherOwner)->post(route('team.roles.store'), ['name' => 'Theirs', 'permissions' => ['orders.view']]);
        $theirs = Role::where('restaurant_id', $other->id)->first();

        $this->actingAs($owner)->put(route('team.roles.update', $mine), ['permissions' => ['menu.manage']])->assertRedirect();
        expect($mine->fresh()->permissions->pluck('name')->all())->toBe(['menu.manage']);

        $this->actingAs($owner)->get(route('team.roles.edit', $theirs))->assertNotFound();
        $this->actingAs($owner)->delete(route('team.roles.destroy', $theirs))->assertNotFound();

        $builtIn = Role::whereNull('restaurant_id')->where('name', 'waiter')->first();
        $this->actingAs($owner)->put(route('team.roles.update', $builtIn), ['permissions' => []])->assertNotFound();
    });

    it('refuses to delete a role somebody still has, then deletes it once free', function () {
        Mail::fake();
        [$r, $owner] = tmShop();
        $this->actingAs($owner)->post(route('team.roles.store'), ['name' => 'Host', 'permissions' => ['orders.view']]);
        $role = Role::where('restaurant_id', $r->id)->first();
        $this->actingAs($owner)->post(route('team.store'), ['name' => 'H', 'email' => 'h@example.com', 'role' => 'Host']);

        $this->actingAs($owner)->delete(route('team.roles.destroy', $role))->assertSessionHasErrors('role');
        expect(Role::find($role->id))->not->toBeNull();

        $h = User::where('email', 'h@example.com')->first();
        $this->actingAs($owner)->put(route('team.update', $h), ['role' => 'waiter']);
        $this->actingAs($owner)->delete(route('team.roles.destroy', $role))->assertRedirect(route('team.roles.index'));
        expect(Role::find($role->id))->toBeNull();
    });
});
