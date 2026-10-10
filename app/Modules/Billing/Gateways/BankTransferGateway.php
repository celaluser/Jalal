<?php

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Payments\CheckoutResult;
use App\Modules\Billing\Payments\GatewayConfig;
use App\Modules\Billing\Payments\PaymentNotification;
use Illuminate\Http\Request;

/**
 * Offline payment: the customer gets the bank details and the invoice stays open until the super
 * admin marks it paid (which then activates the plan).
 */
class BankTransferGateway extends BaseGateway
{
    public function code(): string
    {
        return 'bank_transfer';
    }

    public function name(): string
    {
        return 'Bank transfer / manual';
    }

    public function fields(): array
    {
        return [['key' => 'instructions', 'label' => 'Payment instructions shown to the customer (IBAN, reference...)', 'type' => 'textarea']];
    }

    public function checkout(Invoice $invoice, GatewayConfig $config, string $returnUrl, string $cancelUrl, string $webhookUrl): CheckoutResult
    {
        return new CheckoutResult(null, $invoice->number, str_replace(':reference', $invoice->number, (string) $config->get('instructions')));
    }

    public function webhook(Request $request, GatewayConfig $config): ?PaymentNotification
    {
        return null;
    }

    public function confirmReturn(Request $request, Invoice $invoice, GatewayConfig $config): ?PaymentNotification
    {
        return null;
    }
}
