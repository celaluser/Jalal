<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingSettingsController extends Controller
{
    private const KEYS = ['billing.currency', 'billing.tax_name', 'billing.tax_rate', 'billing.company_name', 'billing.company_address', 'billing.company_tax_id'];

    public function edit(SettingsService $settings): View
    {
        return view('admin::settings.billing', [
            'values' => collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => $settings->get($k)])->all() + ['billing.prices_include_tax' => (bool) $settings->get('billing.prices_include_tax'), 'billing.proration' => (bool) $settings->get('billing.proration', true), 'affiliate.enabled' => (bool) $settings->get('affiliate.enabled', false), 'affiliate.percent' => $settings->get('affiliate.percent', 20)],
            'currencies' => Currency::where('is_active', true)->get(),
        ]);
    }

    public function update(Request $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'currency' => ['required', 'string', 'max:8'],
            'tax_name' => ['nullable', 'string', 'max:40'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_tax_id' => ['nullable', 'string', 'max:60'],
            'affiliate_percent' => ['nullable', 'numeric', 'between:0,100'],
        ]);
        $percent = $data['affiliate_percent'] ?? 20;
        unset($data['affiliate_percent']);

        foreach ($data as $key => $value) {
            $settings->set('billing.'.$key, $value);
        }
        $settings->set('billing.prices_include_tax', $request->boolean('prices_include_tax') ? '1' : '0');
        $settings->set('billing.proration', $request->boolean('proration') ? '1' : '0');
        $settings->set('affiliate.enabled', $request->boolean('affiliate_enabled') ? '1' : '0');
        $settings->set('affiliate.percent', (string) $percent);

        return back()->with('status', __('admin.saved'));
    }
}
