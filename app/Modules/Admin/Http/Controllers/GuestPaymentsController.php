<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Contracts\PaymentGatewayInterface;
use App\Modules\Billing\Payments\GatewayManager;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Orders\Models\OrderPayment;
use App\Modules\Orders\Services\RestaurantGateways;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** The platform's side of restaurants taking online payments: which gateways, what commission, and what is owed. */
class GuestPaymentsController extends Controller
{
    public function __construct(private readonly RestaurantGateways $gateways, private readonly SettingsService $settings) {}

    public function edit(): View
    {
        $stats = OrderPayment::withoutGlobalScopes()->where('method', 'online')->where('status', 'paid')
            ->select('restaurant_id', DB::raw('count(*) as payments'), DB::raw('sum(amount_cents) as volume'), DB::raw('sum(commission_cents) as commission'), DB::raw('sum(case when commission_billed_at is null then commission_cents else 0 end) as unbilled'))
            ->groupBy('restaurant_id')->orderByDesc('volume')->limit(50)->get();

        return view('admin::settings.guest-payments', [
            'manager' => $this->gateways, 'gateways' => $this->gateways->allowed() + array_diff_key($this->all(), $this->gateways->allowed()),
            'stats' => $stats, 'restaurants' => Restaurant::whereIn('id', $stats->pluck('restaurant_id'))->get()->keyBy('id'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['commission_percent' => ['nullable', 'numeric', 'min:0', 'max:50'], 'allowed' => ['nullable', 'array'], 'allowed.*' => ['string', 'max:24']]);
        $this->settings->set('guest_payments.enabled', $request->boolean('enabled') ? '1' : '0');
        $this->settings->set('guest_payments.commission_percent', (string) ($data['commission_percent'] ?? 0));

        foreach (array_keys($this->all()) as $code) {
            $this->settings->set("guest_payments.allowed.{$code}", in_array($code, $data['allowed'] ?? [], true) ? '1' : '0');
        }

        return back()->with('status', __('admin.saved'));
    }

    /** @return array<string, PaymentGatewayInterface> every gateway a restaurant could ever use, allowed or not */
    private function all(): array
    {
        return array_filter(app(GatewayManager::class)->all(), fn ($g) => ! in_array($g->code(), RestaurantGateways::EXCLUDED, true));
    }
}
