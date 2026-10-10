<?php

namespace App\Modules\Team\Services;

use App\Models\User;
use App\Modules\Activity\Services\ActivityLogger;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Staff accounts of one restaurant: invite, change role, switch off, remove. The owner account is
 * fixed, nobody can change their own access, and the plan's staff limit is enforced on invitation.
 * Errors are InvalidArgumentException with a stable code, translated as team.error_{code}.
 */
class TeamService
{
    /** Roles that can be given to staff. The owner role belongs to the account holder only. */
    public const ASSIGNABLE = [Permissions::MANAGER, Permissions::WAITER, Permissions::KITCHEN, Permissions::CASHIER, Permissions::BAR, Permissions::DELIVERY];

    public function __construct(private readonly LimitGuard $limits) {}

    /** @return list<string> role names this restaurant can hand out: built-in plus its own custom roles */
    public function roles(Restaurant $restaurant): array
    {
        return array_merge(self::ASSIGNABLE, $this->customRoles($restaurant)->pluck('name')->all());
    }

    /** @return Collection<int, Role> */
    public function customRoles(Restaurant $restaurant)
    {
        return Role::where('restaurant_id', $restaurant->id)->orderBy('name')->get();
    }

    public function remaining(Restaurant $restaurant): ?int
    {
        return $this->limits->remaining($restaurant, 'staff', $restaurant->users()->count());
    }

    public function roleOf(User $user): ?string
    {
        return $this->inTeam($user->restaurant, fn () => $user->roles()->pluck('name')->first());
    }

    public function isOwner(User $user): bool
    {
        return $user->restaurant && (int) $user->restaurant->owner_id === (int) $user->id;
    }

    /** @throws InvalidArgumentException */
    public function invite(Restaurant $restaurant, string $name, string $email, string $role, User $by): User
    {
        $this->assertRole($restaurant, $role);

        if ($this->remaining($restaurant) === 0) {
            throw new InvalidArgumentException('limit');
        }

        $user = DB::transaction(function () use ($restaurant, $name, $email, $role) {
            $user = User::create([
                'restaurant_id' => $restaurant->id, 'name' => trim($name), 'email' => Str::lower(trim($email)),
                // Nobody knows this password: the person sets their own through the invitation link.
                'password' => Str::random(40), 'locale' => $restaurant->locale,
            ]);
            // Opening the invitation link needs the mailbox, so the address counts as confirmed.
            $user->forceFill(['email_verified_at' => now(), 'invited_at' => now()])->save();
            $this->assign($restaurant, $user, $role);

            return $user;
        });

        $this->sendInvitation($user, $by);
        app(ActivityLogger::class)->record('invited', $user, ['role' => [null, $role]], restaurantId: $restaurant->id);

        return $user;
    }

    public function sendInvitation(User $user, User $by): void
    {
        $token = Password::broker()->createToken($user);

        SafeMail::send($user, new TemplatedMail('staff_invite', [
            'name' => $user->name, 'inviter' => $by->name, 'restaurant' => $user->restaurant->name,
            'role' => (string) $this->roleOf($user), 'action_url' => route('password.reset', ['token' => $token, 'email' => $user->email]),
            'expires_minutes' => (string) config('auth.passwords.users.expire', 60), 'app_name' => config('app.name'),
        ], $user->locale));
    }

    /** @throws InvalidArgumentException */
    public function changeRole(Restaurant $restaurant, User $user, string $role, User $by): void
    {
        $this->assertManageable($user, $by);
        $this->assertRole($restaurant, $role);
        $this->assign($restaurant, $user, $role);
        app(ActivityLogger::class)->record('role changed', $user, ['role' => [null, $role]], restaurantId: $restaurant->id);
    }

    /** @throws InvalidArgumentException */
    public function setDisabled(User $user, bool $disabled, User $by): void
    {
        $this->assertManageable($user, $by);
        $user->forceFill(['disabled_at' => $disabled ? now() : null])->save();
        app(ActivityLogger::class)->record($disabled ? 'switched off' : 'switched on', $user, restaurantId: $user->restaurant_id);

        if ($disabled) {
            // End every session of that person on the spot.
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }

    /** @throws InvalidArgumentException */
    public function remove(Restaurant $restaurant, User $user, User $by): void
    {
        $this->assertManageable($user, $by);
        app(ActivityLogger::class)->record('removed', $user, restaurantId: $restaurant->id);

        DB::transaction(function () use ($restaurant, $user) {
            $this->inTeam($restaurant, fn () => $user->syncRoles([]));
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });
    }

    /** @throws InvalidArgumentException */
    private function assertManageable(User $user, User $by): void
    {
        if ($this->isOwner($user)) {
            throw new InvalidArgumentException('owner');
        }

        if ($user->is($by)) {
            throw new InvalidArgumentException('self');
        }
    }

    /** @throws InvalidArgumentException */
    private function assertRole(Restaurant $restaurant, string $role): void
    {
        if (! in_array($role, $this->roles($restaurant), true)) {
            throw new InvalidArgumentException('role');
        }
    }

    private function assign(Restaurant $restaurant, User $user, string $role): void
    {
        $this->inTeam($restaurant, function () use ($user, $role, $restaurant) {
            // A custom role of this restaurant wins over a global role of the same name; both are looked up inside the team.
            $match = Role::where('name', $role)->where(fn ($q) => $q->where('restaurant_id', $restaurant->id)->orWhereNull('restaurant_id'))->orderByDesc('restaurant_id')->first();
            $user->syncRoles([$match]);
        });
    }

    private function inTeam(?Restaurant $restaurant, callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($restaurant?->id ?? config('tenancy.platform_team_id'));

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
