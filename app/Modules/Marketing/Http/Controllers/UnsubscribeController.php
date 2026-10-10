<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Marketing\Services\CustomerService;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The link at the bottom of every campaign. Signed (not guessable, not editable), works without signing in,
 * and also accepts the one-click POST that mail apps send.
 */
class UnsubscribeController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function show(Request $request, int $customer): View
    {
        [$model, $restaurant] = $this->find($customer);

        return view('marketing::guest.unsubscribe', ['restaurant' => $restaurant, 'done' => $model->unsubscribed_at !== null, 'confirmed' => false, 'url' => $request->fullUrl()]);
    }

    public function store(Request $request, int $customer): View
    {
        [$model, $restaurant] = $this->find($customer);
        $this->customers->unsubscribe($model);

        return view('marketing::guest.unsubscribe', ['restaurant' => $restaurant, 'done' => true, 'confirmed' => true, 'url' => $request->fullUrl()]);
    }

    /** @return array{0: Customer, 1: Restaurant} */
    private function find(int $id): array
    {
        $customer = Customer::allTenants()->findOrFail($id);
        $restaurant = Restaurant::findOrFail($customer->restaurant_id);

        $locale = (string) ($customer->locale ?: $restaurant->locale);

        if (preg_match('/^[a-z]{2}$/', $locale) && is_dir(lang_path($locale))) {
            app()->setLocale($locale);
        }

        return [$customer, $restaurant];
    }
}
