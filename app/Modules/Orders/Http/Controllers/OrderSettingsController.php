<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Services\OrderSettings;
use App\Modules\Orders\Services\TicketPrinter;
use App\Modules\Orders\Support\OrderType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderSettingsController extends Controller
{
    public function __construct(private readonly OrderSettings $settings) {}

    public function edit(Request $request): View
    {
        $restaurant = $request->user()->restaurant;

        return view('orders::settings.edit', ['restaurant' => $restaurant, 's' => $this->settings->for($restaurant), 'printUrl' => url('/print/'.app(TicketPrinter::class)->token($restaurant))]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate($this->settings->rules());

        // Guests must be able to order somehow: at least one order type and one way to pay.
        if (! collect(OrderType::ALL)->contains(fn ($t) => ! empty($data[$t]))) {
            return back()->withInput()->withErrors(['dine_in' => __('orders.need_type')]);
        }

        if (empty($data['pay_cash']) && empty($data['pay_card'])) {
            return back()->withInput()->withErrors(['pay_cash' => __('orders.need_payment')]);
        }

        $this->settings->save($request->user()->restaurant, $data);

        return back()->with('status', __('admin.saved'));
    }
}
