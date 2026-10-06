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
            'values' => collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => $settings->get($k)])->all() + ['billing.prices_include_tax' => (bool) $settings->get('billing.prices_include_tax')],
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
        ]);

        foreach ($data as $key => $value) {
            $settings->set('billing.'.$key, $value);
        }
        $settings->set('billing.prices_include_tax', $request->boolean('prices_include_tax') ? '1' : '0');

        return back()->with('status', __('admin.saved'));
    }
}
