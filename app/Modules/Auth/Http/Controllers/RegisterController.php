<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

/**
 * Restaurant owner self-registration: creates the restaurant (tenant) and its owner together.
 * Plan selection and payment are layered on top in Phase 4.
 */
class RegisterController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function create(): View
    {
        abort_unless($this->enabled(), 404);

        return view('auth-module::register');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->enabled(), 404);

        $data = $request->validate([
            'restaurant_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = DB::transaction(function () use ($data) {
            $restaurant = Restaurant::create([
                'name' => $data['restaurant_name'],
                'slug' => $this->uniqueSlug($data['restaurant_name']),
                'locale' => app()->getLocale(),
                'currency_code' => $this->settings->get('general.default_currency'),
                'timezone' => $this->settings->get('general.timezone', config('app.timezone')),
            ]);

            $user = User::create([
                'restaurant_id' => $restaurant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'locale' => app()->getLocale(),
            ]);

            $restaurant->update(['owner_id' => $user->id]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($restaurant->id);
            $user->assignRole(Permissions::OWNER);

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    private function enabled(): bool
    {
        return (bool) $this->settings->get('auth.registration_enabled', true);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'restaurant';
        $slug = $base;

        while (Restaurant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
