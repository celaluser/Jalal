<?php

namespace App\Modules\Store\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Payments\GatewayManager;
use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Store\Services\Catalog;
use App\Modules\Store\Services\Entitlements;
use App\Modules\Store\Services\StoreAccess;
use App\Modules\Store\Services\StoreCheckout;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The restaurant owner's store: themes and premium features to buy or rent. */
class StoreController extends Controller
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly Entitlements $entitlements,
        private readonly StoreAccess $access,
        private readonly StoreCheckout $checkout,
        private readonly GatewayManager $gateways,
        private readonly LimitGuard $limits,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function index(Request $request): View
    {
        $restaurant = $request->user()->restaurant;
        $items = collect($this->catalog->all())->filter(fn ($i) => $i['visible'] || $this->entitlements->owns($restaurant, $i['slug']))->map(fn ($i) => $i + ['state' => $this->state($restaurant, $i)]);

        return view('store::panel.index', [
            'restaurant' => $restaurant,
            'themes' => $items->where('kind', 'theme'),
            'features' => $items->where('kind', 'feature'),
            'tab' => $request->query('tab') === 'features' ? 'features' : 'themes',
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $restaurant = $request->user()->restaurant;
        $item = $this->item($slug);
        $state = $this->state($restaurant, $item);
        $units = $this->checkout->units($item);

        return view('store::panel.show', [
            'restaurant' => $restaurant, 'item' => $item + ['state' => $state],
            'owned' => $this->entitlements->current($restaurant, $slug),
            'units' => collect($units)->mapWithKeys(fn ($u) => [$u => $this->checkout->quote($item, $u)]),
            'gateways' => $this->gateways->availableFor($item['currency']),
            'canTrial' => $item['trial_days'] > 0 && $this->catalog->purchasable($item) && ! $this->entitlements->everHad($restaurant, $slug) && $state['status'] === 'buy',
            'plan' => $this->limits->plan($restaurant),
        ]);
    }

    public function buy(Request $request, string $slug): RedirectResponse
    {
        $item = $this->item($slug);
        $data = $request->validate([
            'gateway' => ['required', 'string', Rule::in(array_keys($this->gateways->availableFor($item['currency'])))],
            'units' => ['nullable', 'integer', Rule::in($this->checkout->units($item))],
        ]);

        try {
            $outcome = $this->checkout->start($request->user()->restaurant, $slug, (int) ($data['units'] ?? 1), $data['gateway']);
        } catch (BillingException $e) {
            return back()->withInput()->withErrors(['checkout' => $e->getMessage()]);
        }

        if ($outcome['result'] === null) {
            return redirect()->route('store.show', $slug)->with('status', __('store.unlocked'));
        }

        if ($outcome['result']->redirectUrl) {
            return redirect()->away($outcome['result']->redirectUrl);
        }

        return redirect()->route('store.show', $slug)->with('instructions', ['invoice' => $outcome['invoice']->number, 'text' => $outcome['result']->instructions]);
    }

    public function trial(Request $request, string $slug): RedirectResponse
    {
        $this->item($slug);

        return $this->entitlements->startTrial($request->user()->restaurant, $slug)
            ? redirect()->route('store.show', $slug)->with('status', __('store.trial_started'))
            : redirect()->route('store.show', $slug)->withErrors(['checkout' => __('store.trial_unavailable')]);
    }

    /** @return array<string, mixed> */
    private function item(string $slug): array
    {
        $item = $this->catalog->find($slug) ?? abort(404);
        abort_unless($item['visible'] || $this->entitlements->owns(request()->user()->restaurant, $slug), 404);

        return $item;
    }

    /**
     * Where this restaurant stands with an item.
     *
     * @param  array<string, mixed>  $item
     * @return array{status: string, until: ?Carbon}
     *                                               status: free | plan | owned | buy | upgrade
     */
    private function state(Restaurant $restaurant, array $item): array
    {
        if ($item['kind'] === 'feature') {
            $includedByPlan = (bool) $this->limits->plan($restaurant)?->hasFeature($item['key']);
            $owned = $this->entitlements->current($restaurant, $item['slug']);

            return match (true) {
                $item['mode'] === 'free' => ['status' => 'free', 'until' => null],
                $includedByPlan => ['status' => 'plan', 'until' => null],
                $owned !== null => ['status' => 'owned', 'until' => $owned->ends_at],
                $this->catalog->purchasable($item) => ['status' => 'buy', 'until' => null],
                default => ['status' => 'upgrade', 'until' => null],
            };
        }

        $owned = $this->entitlements->current($restaurant, $item['slug']);

        return match (true) {
            $item['mode'] === 'free' => ['status' => 'free', 'until' => null],
            $owned !== null => ['status' => 'owned', 'until' => $owned->ends_at],
            $this->access->themeAllowed($restaurant, $item['key']) => ['status' => 'plan', 'until' => null],
            $this->catalog->purchasable($item) => ['status' => 'buy', 'until' => null],
            default => ['status' => 'upgrade', 'until' => null],
        };
    }
}
