<?php

namespace App\Modules\Store\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Store\Models\Entitlement;
use App\Modules\Store\Services\Catalog;
use App\Modules\Store\Services\Entitlements;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The platform's side of the store: what is free, what is for sale at what price, and who owns what. */
class AdminStoreController extends Controller
{
    public function __construct(private readonly Catalog $catalog, private readonly Entitlements $entitlements) {}

    public function index(Request $request): View
    {
        $kind = in_array($request->query('kind'), ['feature', 'theme', 'bundle'], true) ? $request->query('kind') : null;
        $owners = Entitlement::allTenants()->live()->selectRaw('item_slug, count(*) as n')->groupBy('item_slug')->pluck('n', 'item_slug');

        return view('store::admin.index', [
            'items' => collect($this->catalog->all())->when($kind, fn ($c) => $c->where('kind', $kind)),
            'kind' => $kind, 'owners' => $owners,
        ]);
    }

    public function edit(string $slug): View
    {
        return view('store::admin.edit', $this->editData($this->catalog->find($slug) ?? abort(404)));
    }

    public function createBundle(): View
    {
        $currency = strtoupper((string) app(SettingsService::class)->get('billing.currency', 'USD'));

        return view('store::admin.edit', $this->editData(['slug' => null, 'kind' => 'bundle', 'key' => '', 'name' => '', 'summary' => '', 'description' => '', 'mode' => 'paid', 'billing' => 'monthly', 'price' => 0.0, 'currency' => $currency, 'trial_days' => 0, 'visible' => true, 'sort' => 0, 'customised' => false, 'items' => []]));
    }

    public function storeBundle(Request $request): RedirectResponse
    {
        $request->merge(['mode' => 'paid']); // a bundle is always for sale
        $data = $request->validate($this->catalog->rules() + ['items' => ['required', 'array', 'min:2'], 'items.*' => ['string']]);

        if ((float) $data['price'] <= 0 || trim((string) ($data['name'] ?? '')) === '') {
            return back()->withInput()->withErrors(['price' => __('store.admin.bundle_needs')]);
        }

        $this->catalog->createBundle($data['items'], ['mode' => 'paid'] + $data);

        return redirect()->route('admin.store.index', ['kind' => 'bundle'])->with('status', __('store.admin.saved'));
    }

    /** @return array<string, mixed> */
    private function editData(array $item): array
    {
        return ['item' => $item, 'currencies' => Currency::where('is_active', true)->pluck('code'), 'choices' => collect($this->catalog->all())->where('kind', '!=', 'bundle')];
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $this->catalog->find($slug) ?? abort(404);

        if (str_starts_with($slug, 'bundle:')) {
            $request->merge(['mode' => 'paid']);
        }

        $data = $request->validate($this->catalog->rules());

        if ($data['mode'] === 'paid' && (float) $data['price'] <= 0) {
            return back()->withInput()->withErrors(['price' => __('store.admin.price_needed')]);
        }

        if (str_starts_with($slug, 'bundle:')) {
            $request->validate(['items' => ['required', 'array', 'min:2'], 'items.*' => ['string'], 'name' => ['required', 'string', 'max:80']]);
        }

        $this->catalog->save($slug, $data);

        if (str_starts_with($slug, 'bundle:')) {
            $this->catalog->setBundleItems($slug, (array) $request->input('items'));
        }

        return redirect()->route('admin.store.index')->with('status', __('store.admin.saved'));
    }

    public function reset(string $slug): RedirectResponse
    {
        $this->catalog->find($slug) ?? abort(404);
        $this->catalog->reset($slug);

        return redirect()->route('admin.store.index')->with('status', __('store.admin.reset_done'));
    }

    public function sales(Request $request): View
    {
        $rows = Entitlement::allTenants()->with([])->latest('id')->limit(200)->get();
        $restaurants = Restaurant::withTrashed()->whereIn('id', $rows->pluck('restaurant_id'))->pluck('name', 'id');

        return view('store::admin.sales', [
            'rows' => $rows, 'restaurants' => $restaurants, 'items' => $this->catalog->all(),
            'allRestaurants' => Restaurant::orderBy('name')->limit(500)->pluck('name', 'id'),
            'revenue' => Invoice::allTenants()->whereNotNull('store_slug')->where('status', 'paid')->selectRaw('currency_code, sum(total) as total, count(*) as n')->groupBy('currency_code')->get(),
        ]);
    }

    public function grant(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'], 'item' => ['required', 'string'],
            'months' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);
        $this->catalog->find($data['item']) ?? abort(422);
        $restaurant = Restaurant::findOrFail($data['restaurant_id']);

        $this->entitlements->grant($restaurant, $data['item'], isset($data['months']) ? (int) $data['months'] : null, 'admin');

        return back()->with('status', __('store.admin.granted'));
    }

    public function revoke(int $entitlement): RedirectResponse
    {
        $this->entitlements->revoke(Entitlement::allTenants()->findOrFail($entitlement));

        return back()->with('status', __('store.admin.revoked'));
    }
}
