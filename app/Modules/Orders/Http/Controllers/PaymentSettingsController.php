<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Services\OrderSettings;
use App\Modules\Orders\Services\RestaurantGateways;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Where a restaurant connects its own payment accounts to take guests' money online. */
class PaymentSettingsController extends Controller
{
    public function __construct(private readonly RestaurantGateways $gateways) {}

    public function edit(Request $request): View
    {
        $restaurant = $request->user()->restaurant;

        return view('orders::settings.payments', ['restaurant' => $restaurant, 'gateways' => $this->gateways->allowed(), 'manager' => $this->gateways, 'permitted' => $this->gateways->permitted($restaurant), 'commission' => $this->gateways->commissionPercent()]);
    }

    public function update(Request $request, string $gateway): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        $driver = $this->gateways->find($gateway);
        abort_if($driver === null, 404);
        abort_unless($this->gateways->permitted($restaurant), 403);

        $rules = [];

        foreach ($driver->fields() as $field) {
            $rules[$field['key']] = match ($field['type']) {
                'select' => ['nullable', 'in:'.implode(',', array_keys($field['options']))],
                'textarea' => ['nullable', 'string', 'max:2000'],
                default => ['nullable', 'string', 'max:500'],
            };
        }

        $this->gateways->save($restaurant, $gateway, $request->boolean('enabled'), $request->validate($rules));
        app(OrderSettings::class); // payment methods are read fresh from the gateways on every request

        if ($request->boolean('enabled') && ! $this->gateways->ready($restaurant, $gateway)) {
            return back()->with('status', __('orders.gateway_saved_incomplete', ['name' => $driver->name()]));
        }

        return back()->with('status', __('admin.saved'));
    }
}
