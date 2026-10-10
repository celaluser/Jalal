<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Tenancy\Models\Restaurant;
use App\Modules\Tenancy\Services\DomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class RestaurantController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = $request->query('status');
        $planId = $request->query('plan');

        $restaurants = Restaurant::query()
            ->with('owner')
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where('name', 'like', $like)->orWhere('slug', 'like', $like)
                    ->orWhereHas('owner', fn ($o) => $o->where('email', 'like', $like));
            }))
            ->when(in_array($status, [Restaurant::STATUS_ACTIVE, Restaurant::STATUS_SUSPENDED], true), fn ($q) => $q->where('status', $status))
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
            'filters' => ['q' => $search, 'status' => $status, 'plan' => $planId, 'trashed' => $request->boolean('trashed')],
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
        ]);
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
