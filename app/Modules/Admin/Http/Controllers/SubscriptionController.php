<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');

        return view('admin::subscriptions.index', [
            'subscriptions' => Subscription::allTenants()->with('plan')
                ->when(in_array($status, ['trialing', 'active', 'past_due', 'canceled', 'expired'], true), fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(25)->withQueryString(),
            'restaurants' => Restaurant::pluck('name', 'id'),
            'status' => $status,
        ]);
    }

    /** Manual assignment by the admin; optionally records a paid invoice for the books. */
    public function store(Request $request, InvoiceService $invoices): RedirectResponse
    {
        $data = $request->validate([
            'restaurant_id' => ['required', Rule::exists('restaurants', 'id')->whereNull('deleted_at')],
            'plan_id' => ['required', 'exists:plans,id'],
            'ends_at' => ['nullable', 'date', 'after:now'],
            'create_invoice' => ['nullable', 'boolean'],
        ]);

        $restaurant = Restaurant::findOrFail($data['restaurant_id']);
        $plan = Plan::findOrFail($data['plan_id']);

        $subscription = $this->subscriptions->assign(
            $restaurant, $plan, ! empty($data['ends_at']) ? Carbon::parse($data['ends_at']) : null, ['gateway' => 'manual']
        );

        if ($request->boolean('create_invoice')) {
            $invoices->markPaid($invoices->create($restaurant, $plan, $subscription), 'manual');
        }

        return back()->with('status', __('admin.subscriptions.assigned'));
    }

    public function renew(int $subscription): RedirectResponse
    {
        $this->subscriptions->renew($this->find($subscription));

        return back()->with('status', __('admin.subscriptions.renewed'));
    }

    public function cancel(Request $request, int $subscription): RedirectResponse
    {
        $this->subscriptions->cancel($this->find($subscription), $request->boolean('immediately'));

        return back()->with('status', __('admin.subscriptions.canceled'));
    }

    /** Route binding would apply the tenant scope and 404; the admin looks across all tenants on purpose. */
    private function find(int $id): Subscription
    {
        return Subscription::allTenants()->with('plan')->findOrFail($id);
    }
}
