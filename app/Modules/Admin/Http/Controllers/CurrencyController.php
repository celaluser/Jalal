<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Currencies restaurants can price in: symbol, where it goes, decimals and separators. */
class CurrencyController extends Controller
{
    public function index(): View
    {
        return view('admin::localization.currencies', ['currencies' => Currency::orderByDesc('is_default')->orderBy('code')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $data['code'] = strtoupper($data['code']);
        Currency::create($data + ['is_active' => true]);

        return back()->with('status', __('admin.localization.currency_added'));
    }

    public function update(Request $request, Currency $currency): RedirectResponse
    {
        $data = $this->validated($request, $currency);
        $currency->update(['name' => $data['name'], 'symbol' => $data['symbol'], 'symbol_position' => $data['symbol_position'], 'decimals' => $data['decimals'], 'decimal_separator' => $data['decimal_separator'], 'thousands_separator' => $data['thousands_separator'], 'is_active' => $currency->is_default ? true : $request->boolean('is_active')]);

        return back()->with('status', __('admin.saved'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Currency $currency): array
    {
        return $request->validate([
            'code' => $currency ? ['prohibited'] : ['required', 'alpha', 'size:3', Rule::unique('currencies', 'code')],
            'name' => ['required', 'string', 'max:80'], 'symbol' => ['required', 'string', 'max:12'],
            'symbol_position' => ['required', 'in:before,after'], 'decimals' => ['required', 'integer', 'between:0,4'],
            'decimal_separator' => ['required', 'string', 'max:1'], 'thousands_separator' => ['nullable', 'string', 'max:1'],
        ]) + ['thousands_separator' => ''];
    }
}
