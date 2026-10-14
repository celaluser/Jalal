<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Models\Plan;
use App\Modules\Core\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('admin::plans.index', ['plans' => Plan::withCount('subscriptions')->orderBy('sort')->orderBy('price')->get()]);
    }

    public function create(): View
    {
        return view('admin::plans.form', ['plan' => new Plan(['interval' => 'monthly', 'currency_code' => 'USD', 'is_active' => true]), 'currencies' => Currency::where('is_active', true)->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Plan::create($this->validated($request));

        return redirect()->route('admin.plans.index')->with('status', __('admin.saved'));
    }

    public function edit(Plan $plan): View
    {
        return view('admin::plans.form', ['plan' => $plan, 'currencies' => Currency::where('is_active', true)->get()]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request, $plan));

        return redirect()->route('admin.plans.index')->with('status', __('admin.saved'));
    }

    /** A plan with subscribers is deactivated instead: history and invoices keep pointing at it. */
    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            $plan->update(['is_active' => false]);

            return back()->with('status', __('admin.plans.deactivated_instead'));
        }

        $plan->delete();

        return back()->with('status', __('admin.plans.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('plans', 'slug')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'interval' => ['required', Rule::in(Plan::INTERVALS)],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'currency_code' => ['required', 'string', 'max:8'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'limits' => ['nullable', 'array'],
            'limits.*' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
        ]);

        // A blank limit means unlimited and is stored as null; unknown keys are dropped.
        $limits = [];
        foreach (Plan::LIMITS as $key) {
            $value = $data['limits'][$key] ?? null;
            $limits[$key] = ($value === null || $value === '') ? null : (int) $value;
        }

        $features = [];
        foreach (Plan::FEATURES as $key) {
            $features[$key] = $request->boolean("features.$key");
        }

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'interval' => $data['interval'],
            'price' => $data['interval'] === 'free' ? 0 : $data['price'],
            'currency_code' => $data['currency_code'],
            'trial_days' => (int) ($data['trial_days'] ?? 0),
            'sort' => (int) ($data['sort'] ?? 0),
            'limits' => $limits,
            'features' => $features,
            'is_active' => $request->boolean('is_active'),
            'is_featured' => $request->boolean('is_featured'),
        ];
    }
}
