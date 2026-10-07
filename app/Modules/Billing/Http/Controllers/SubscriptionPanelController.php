<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Payments\GatewayManager;
use App\Modules\Billing\Services\CheckoutService;
use App\Modules\Billing\Services\CouponService;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Billing\Services\PlanOnboarding;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Billing\Services\UsageReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The restaurant owner's view of their subscription: plan, usage, checkout, invoices. */
class SubscriptionPanelController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly InvoiceService $invoices,
        private readonly GatewayManager $gateways,
    ) {}

    public function index(Request $request, UsageReport $usage): View
    {
        $restaurant = $request->user()->restaurant;
        $subscription = $this->subscriptions->current($restaurant);

        return view('billing::panel.index', [
            'subscription' => $subscription,
            'plan' => $subscription?->plan,
            'usage' => $usage->for($restaurant),
            'plans' => Plan::active()->get(),
            'invoices' => Invoice::latest('id')->limit(24)->get(),
            'triedTrial' => $this->subscriptions->history($restaurant)->whereNotNull('trial_ends_at')->pluck('plan_id')->all(),
        ]);
    }

    /** Free plans and trials switch immediately; anything else goes to checkout. */
    public function select(Request $request, string $plan, PlanOnboarding $onboarding): RedirectResponse
    {
        $model = $this->plan($plan);
        $restaurant = $request->user()->restaurant;

        if ($this->subscriptions->current($restaurant)?->plan_id === $model->id) {
            return redirect()->route('billing.index');
        }

        return match ($onboarding->activate($restaurant, $model)) {
            PlanOnboarding::CHECKOUT => redirect()->route('billing.checkout', $model->slug),
            default => redirect()->route('billing.index')->with('status', __('billing.plan_changed', ['plan' => $model->name])),
        };
    }

    public function checkout(Request $request, string $plan): View|RedirectResponse
    {
        $model = $this->plan($plan);

        if ($model->isFree()) {
            return redirect()->route('billing.index');
        }

        return view('billing::panel.checkout', $this->summary($model, null) + [
            'plan' => $model,
            'gateways' => $this->gateways->availableFor($model->currency_code),
            'coupon' => (string) $request->query('coupon', ''),
        ]);
    }

    public function start(Request $request, string $plan, CheckoutService $checkout, CouponService $coupons): RedirectResponse
    {
        $model = $this->plan($plan);
        $data = $request->validate([
            'gateway' => ['required', 'string', Rule::in(array_keys($this->gateways->availableFor($model->currency_code)))],
            'coupon' => ['nullable', 'string', 'max:60'],
        ]);

        try {
            $outcome = $checkout->start($request->user()->restaurant, $model, $data['gateway'], $data['coupon'] ?: null);
        } catch (BillingException $e) {
            return back()->withInput()->withErrors(['checkout' => $e->getMessage()]);
        }

        if ($outcome['result'] === null) {
            return redirect()->route('billing.index')->with('status', __('billing.payment_received'));
        }

        if ($outcome['result']->redirectUrl) {
            return redirect()->away($outcome['result']->redirectUrl);
        }

        // Offline method (bank transfer): show the instructions on the subscription page.
        return redirect()->route('billing.index')->with('instructions', ['invoice' => $outcome['invoice']->number, 'text' => $outcome['result']->instructions]);
    }

    /** Live preview of a coupon on the checkout page. */
    public function preview(Request $request, string $plan, CouponService $coupons)
    {
        $model = $this->plan($plan);

        try {
            $coupon = $coupons->validate((string) $request->query('coupon'), $model);
        } catch (BillingException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true] + collect($this->summary($model, $coupon))->only('lines')->all());
    }

    public function cancel(Request $request): RedirectResponse
    {
        $subscription = $this->subscriptions->current($request->user()->restaurant);
        abort_if($subscription === null || $subscription->canceled_at !== null, 404);

        $this->subscriptions->cancel($subscription);

        return back()->with('status', __('billing.canceled'));
    }

    public function pdf(int $invoice)
    {
        $model = Invoice::findOrFail($invoice); // tenant-scoped: other restaurants' invoices are 404

        return $this->invoices->pdf($model)->download($model->number.'.pdf');
    }

    private function plan(string $slug): Plan
    {
        return Plan::active()->where('slug', $slug)->firstOrFail();
    }

    /** @return array{lines: array<string, string>} formatted amounts for the checkout card */
    private function summary(Plan $plan, $coupon): array
    {
        $calc = $this->invoices->calculate($plan->price, $coupon);
        $fmt = fn (int $cents) => number_format($cents / 100, 2).' '.$plan->currency_code;

        return ['lines' => [
            'subtotal' => $fmt($calc['subtotal']),
            'discount' => $calc['discount'] ? '−'.$fmt($calc['discount']) : '',
            'tax' => $calc['tax'] ? $fmt($calc['tax']) : '',
            'tax_rate' => $calc['rate'] > 0 ? rtrim(rtrim(number_format($calc['rate'], 2), '0'), '.').'%' : '',
            'inclusive' => $calc['inclusive'] ? '1' : '',
            'total' => $fmt($calc['total']),
        ]];
    }
}
