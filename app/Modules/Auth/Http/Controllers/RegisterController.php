<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Affiliate\Services\AffiliateService;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\PlanOnboarding;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

/**
 * Restaurant owner self-registration: creates the restaurant (tenant) and its owner together.
 * The chosen plan is activated through PlanOnboarding (trial, free, or pay after verifying the e-mail).
 */
class RegisterController extends Controller
{
    public function __construct(private readonly SettingsService $settings, private readonly PlanOnboarding $onboarding) {}

    public function create(Request $request): View
    {
        abort_unless($this->enabled(), 404);

        $plans = Plan::active()->get();
        $selected = $plans->firstWhere('slug', old('plan', $request->query('plan'))) ?? $this->onboarding->defaultPlan();

        // A referral link: remember who sent this visitor until they sign up.
        if ($ref = $request->query('ref')) {
            $request->session()->put('referral_code', Str::upper(Str::limit(preg_replace('/[^A-Za-z0-9]/', '', (string) $ref), 12, '')));
        }

        return view('auth-module::register', ['plans' => $plans, 'selected' => $selected]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->enabled(), 404);

        $data = $request->validate([
            'restaurant_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'plan' => ['nullable', Rule::exists('plans', 'slug')->where('is_active', true)->whereNull('deleted_at')],
        ]);

        $plan = ! empty($data['plan']) ? Plan::active()->where('slug', $data['plan'])->first() : null;

        [$user, $plan, $outcome] = DB::transaction(function () use ($data, $plan, $request) {
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

            app(AffiliateService::class)->attach($restaurant, $request->session()->pull('referral_code'));

            $outcome = $plan ? $this->onboarding->activate($restaurant, $plan) : null;

            return [$user, $plan, $outcome];
        });

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        // A paid plan without a trial is paid for right after the e-mail address is confirmed.
        if ($outcome === PlanOnboarding::CHECKOUT) {
            $request->session()->put('url.intended', route('billing.checkout', $plan->slug));
        }

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
