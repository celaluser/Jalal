<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Menu\Services\ThemeLibrary;
use App\Modules\Tenancy\Models\Restaurant;
use App\Modules\Tenancy\Services\DomainService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

class RestaurantController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = $request->query('status');
        $planId = $request->query('plan');
        $flag = $request->query('flag');

        $restaurants = Restaurant::query()
            ->with('owner')
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where('name', 'like', $like)->orWhere('slug', 'like', $like)
                    ->orWhereHas('owner', fn ($o) => $o->where('email', 'like', $like));
            }))
            ->when(in_array($status, [Restaurant::STATUS_ACTIVE, Restaurant::STATUS_SUSPENDED], true), fn ($q) => $q->where('status', $status))
            ->when(in_array($flag, ['featured', 'premium', 'limited', 'locked'], true), fn ($q) => $q->where(['featured' => 'is_featured', 'premium' => 'is_premium', 'limited' => 'is_limited', 'locked' => 'theme_locked'][$flag], true))
            ->when($planId, fn ($q) => $q->whereIn('id', Subscription::allTenants()
                ->where('plan_id', (int) $planId)->whereIn('status', Subscription::ACCESS_STATUSES)->select('restaurant_id')))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $current = Subscription::allTenants()->with('plan')
            ->whereIn('restaurant_id', $restaurants->pluck('id'))
            ->whereIn('status', Subscription::ACCESS_STATUSES)
            ->orderBy('id')->get()->keyBy('restaurant_id');

        return view('admin::restaurants.index', [
            'restaurants' => $restaurants,
            'current' => $current,
            'plans' => Plan::orderBy('name')->get(),
            'filters' => ['q' => $search, 'status' => $status, 'plan' => $planId, 'flag' => $flag, 'trashed' => $request->boolean('trashed')],
        ]);
    }

    public function show(Restaurant $restaurant, SubscriptionService $subscriptions): View
    {
        return view('admin::restaurants.show', [
            'restaurant' => $restaurant->load('owner'),
            'subscription' => $subscriptions->current($restaurant),
            'history' => $subscriptions->history($restaurant),
            'invoices' => Invoice::allTenants()->where('restaurant_id', $restaurant->id)->latest('id')->limit(10)->get(),
            'staff' => $restaurant->users()->orderBy('name')->get(),
            'plans' => Plan::active()->get(),
            'themes' => collect(app(ThemeLibrary::class)->all())->map(fn ($t, $key) => $t['name'])->all(),
        ]);
    }

    public function create(): View
    {
        $settings = app(SettingsService::class);

        return view('admin::restaurants.create', [
            'plans' => Plan::active()->orderBy('name')->get(),
            'languages' => Language::where('is_active', true)->orderBy('name')->pluck('name', 'code')->all(),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->pluck('name', 'code')->all(),
            'defaults' => ['locale' => app()->getLocale(), 'currency' => $settings->get('general.default_currency'), 'timezone' => $settings->get('general.timezone', config('app.timezone'))],
            'themes' => collect(app(ThemeLibrary::class)->all())->map(fn ($t) => $t['name'])->all(),
        ]);
    }

    /** The platform owner adds a restaurant (and its owner account) by hand: no sign-up form, no e-mail confirmation round trip. */
    public function store(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate([
            'restaurant_name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('restaurants', 'slug')],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
            'locale' => ['required', Rule::exists('languages', 'code')],
            'currency_code' => ['nullable', Rule::exists('currencies', 'code')],
            'timezone' => ['nullable', Rule::in(timezone_identifiers_list())],
            'plan_id' => ['nullable', Rule::exists('plans', 'id')->whereNull('deleted_at')],
            'start_trial' => ['nullable', 'boolean'],
            'ends_at' => ['nullable', 'date', 'after:now'],
            'is_premium' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'badge' => ['nullable', Rule::in(Restaurant::BADGES)],
            'theme' => ['nullable', Rule::in(array_keys(app(ThemeLibrary::class)->all()))],
        ]);

        $generated = empty($data['password']);

        $restaurant = DB::transaction(function () use ($data, $subscriptions, $request) {
            $restaurant = Restaurant::create([
                'name' => trim($data['restaurant_name']),
                'slug' => ($data['slug'] ?? null) ?: $this->uniqueSlug($data['restaurant_name']),
                'locale' => $data['locale'],
                'currency_code' => $data['currency_code'] ?? null,
                'timezone' => $data['timezone'] ?? config('app.timezone'),
                'theme' => $data['theme'] ?? app(ThemeLibrary::class)->defaultKey(),
                'is_premium' => $request->boolean('is_premium'),
                'is_featured' => $request->boolean('is_featured'),
                'badge' => $data['badge'] ?? null,
            ]);

            $owner = User::create([
                'restaurant_id' => $restaurant->id, 'name' => trim($data['owner_name']), 'email' => $data['owner_email'],
                'password' => $data['password'] ?? Str::random(40), 'locale' => $data['locale'],
            ]);
            $owner->forceFill(['email_verified_at' => now()])->save();
            $restaurant->update(['owner_id' => $owner->id]);

            $registrar = app(PermissionRegistrar::class);
            $registrar->setPermissionsTeamId($restaurant->id);
            $owner->assignRole(Permissions::OWNER);
            $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));

            if (! empty($data['plan_id'])) {
                $plan = Plan::findOrFail($data['plan_id']);

                if ($request->boolean('start_trial') && $plan->trial_days > 0) {
                    $subscriptions->startTrial($restaurant, $plan);
                } else {
                    $subscriptions->assign($restaurant, $plan, ! empty($data['ends_at']) ? Carbon::parse($data['ends_at']) : null, ['gateway' => 'manual']);
                }
            }

            return $restaurant;
        });

        // No password typed: the owner gets the usual "choose your password" e-mail.
        if ($generated) {
            Password::broker()->sendResetLink(['email' => $data['owner_email']]);
        }

        return redirect()->route('admin.restaurants.show', $restaurant->id)->with('status', $generated ? __('platform.created_invited') : __('platform.created'));
    }

    /** What the platform owner decides about one restaurant: showcase, badge, custom limits and features, theme. */
    public function options(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $data = $request->validate([
            'is_premium' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'featured_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'badge' => ['nullable', Rule::in(Restaurant::BADGES)],
            'is_limited' => ['nullable', 'boolean'],
            'limits' => ['nullable', 'array'],
            'limits.*' => ['nullable', 'regex:/^(\d{1,9}|unlimited)$/'],
            'features' => ['nullable', 'array'],
            'features.*' => ['nullable', 'in:,on,off'],
            'theme' => ['nullable', Rule::in(array_keys(app(ThemeLibrary::class)->all()))],
            'theme_locked' => ['nullable', 'boolean'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $limits = collect(Plan::LIMITS)->mapWithKeys(fn ($key) => [$key => $data['limits'][$key] ?? null])->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => $v === 'unlimited' ? 'unlimited' : (int) $v)->all();
        $features = collect(Plan::FEATURES)->mapWithKeys(fn ($key) => [$key => $data['features'][$key] ?? ''])->filter(fn ($v) => $v !== '')->map(fn ($v) => $v === 'on')->all();

        $restaurant->update([
            'is_premium' => $request->boolean('is_premium'), 'is_featured' => $request->boolean('is_featured'), 'featured_order' => (int) ($data['featured_order'] ?? 0),
            'badge' => $data['badge'] ?? null, 'is_limited' => $request->boolean('is_limited'), 'limit_overrides' => $limits ?: null, 'feature_overrides' => $features ?: null,
            'theme' => ($data['theme'] ?? null) ?: $restaurant->theme, 'theme_locked' => $request->boolean('theme_locked'), 'admin_notes' => $data['admin_notes'] ?? null,
        ]);

        return back()->with('status', __('admin.saved'));
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

    public function update(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('restaurants', 'slug')->ignore($restaurant->id)],
            'locale' => ['required', 'string', 'max:12'],
        ]);

        $restaurant->update($data);

        return back()->with('status', __('admin.saved'));
    }

    /** Platform owner override: set or fix an address, and mark it verified by hand (e.g. after checking DNS elsewhere). */
    public function domain(Request $request, Restaurant $restaurant, DomainService $domains): RedirectResponse
    {
        $data = $request->validate(['subdomain' => ['nullable', 'string', 'max:40'], 'custom_domain' => ['nullable', 'string', 'max:255'], 'verified' => ['nullable', 'boolean']]);

        try {
            $domains->setSubdomain($restaurant, $data['subdomain'] ?? null, force: true);
            $domains->setCustomDomain($restaurant, $data['custom_domain'] ?? null, force: true);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['domain' => __('domains.error_'.$e->getMessage())]);
        }

        if ($restaurant->custom_domain) {
            $request->boolean('verified') ? $restaurant->forceFill(['domain_verified_at' => $restaurant->domain_verified_at ?? now()])->save() : $domains->unverify($restaurant);
        }

        return back()->with('status', __('admin.saved'));
    }

    public function verifyDomain(Restaurant $restaurant, DomainService $domains): RedirectResponse
    {
        return $domains->verify($restaurant) ? back()->with('status', __('domains.verified_now')) : back()->withErrors(['domain' => __('domains.not_found_yet')]);
    }

    public function suspend(Restaurant $restaurant): RedirectResponse
    {
        $restaurant->update(['status' => Restaurant::STATUS_SUSPENDED, 'suspended_at' => now()]);

        return back()->with('status', __('admin.restaurants.suspended'));
    }

    public function unsuspend(Restaurant $restaurant): RedirectResponse
    {
        $restaurant->update(['status' => Restaurant::STATUS_ACTIVE, 'suspended_at' => null]);

        return back()->with('status', __('admin.restaurants.unsuspended'));
    }

    /** Soft delete: the data stays recoverable, the restaurant and its staff are locked out. */
    public function destroy(Restaurant $restaurant): RedirectResponse
    {
        $restaurant->delete();

        return redirect()->route('admin.restaurants.index')->with('status', __('admin.restaurants.deleted_msg'));
    }

    public function restore(int $id): RedirectResponse
    {
        $restaurant = Restaurant::onlyTrashed()->findOrFail($id);
        $restaurant->restore();

        return redirect()->route('admin.restaurants.show', $restaurant)->with('status', __('admin.restaurants.restored'));
    }
}
