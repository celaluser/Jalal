<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Payments\GatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentSettingsController extends Controller
{
    public function __construct(private readonly GatewayManager $gateways) {}

    public function edit(): View
    {
        return view('admin::settings.payments', ['gateways' => $this->gateways->all(), 'manager' => $this->gateways]);
    }

    public function update(Request $request, string $gateway): RedirectResponse
    {
        $driver = $this->gateways->find($gateway);
        abort_if($driver === null, 404);

        $rules = [];

        foreach ($driver->fields() as $field) {
            $rules[$field['key']] = match ($field['type']) {
                'select' => ['nullable', 'in:'.implode(',', array_keys($field['options']))],
                'textarea' => ['nullable', 'string', 'max:2000'],
                default => ['nullable', 'string', 'max:500'],
            };
        }

        $values = $request->validate($rules);

        $this->gateways->save($gateway, $request->boolean('enabled'), $values);

        // Turning a gateway on without its keys would offer customers a method that cannot work.
        if ($request->boolean('enabled') && ! $this->gateways->isReady($gateway)) {
            return back()->with('status', __('admin.payments.saved_incomplete', ['name' => $driver->name()]));
        }

        return back()->with('status', __('admin.saved'));
    }
}
