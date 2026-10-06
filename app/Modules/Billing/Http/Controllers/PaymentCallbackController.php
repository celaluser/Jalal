<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Payments\GatewayManager;
use App\Modules\Billing\Services\PaymentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly PaymentProcessor $processor,
    ) {}

    /**
     * Server-to-server callback. The gateway proves authenticity (signature or API re-fetch);
     * failures are 400 (do not retry), processing errors bubble up as 500 so the gateway retries.
     */
    public function webhook(Request $request, string $gateway): JsonResponse
    {
        $driver = $this->gateways->find($gateway);
        abort_if($driver === null || ! $this->gateways->isEnabled($gateway), 404);

        try {
            $notification = $driver->webhook($request, $this->gateways->config($gateway));
        } catch (GatewayException $e) {
            Log::warning('Rejected payment webhook', ['gateway' => $gateway, 'reason' => $e->getMessage()]);

            return response()->json(['ok' => false], 400);
        }

        if ($notification !== null) {
            $this->processor->handle($notification);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * The customer comes back from the hosted payment page (GET, or POST for iyzico). Confirmation
     * is always re-checked with the gateway, so this URL cannot be used to fake a payment.
     */
    public function return(Request $request, string $gateway, string $invoice): RedirectResponse
    {
        $driver = $this->gateways->find($gateway);
        abort_if($driver === null || ! $this->gateways->isEnabled($gateway), 404);

        $model = Invoice::allTenants()->where('number', $invoice)->firstOrFail();

        try {
            $notification = $driver->confirmReturn($request, $model, $this->gateways->config($gateway));

            if ($notification !== null && $notification->invoiceNumber === $model->number) {
                $this->processor->handle($notification);
            }
        } catch (GatewayException $e) {
            Log::warning('Payment return could not be confirmed', ['gateway' => $gateway, 'invoice' => $invoice, 'reason' => $e->getMessage()]);
        }

        $paid = $model->fresh()->status === 'paid';

        return redirect()->route('dashboard')->with('status', $paid ? __('billing.payment_received') : __('billing.payment_pending'));
    }
}
