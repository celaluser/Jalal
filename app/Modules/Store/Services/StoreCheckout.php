<?php

namespace App\Modules\Store\Services;

use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\CheckoutService;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Tenancy\Models\Restaurant;

/** Buying (or renting) a store item: an invoice first, then the usual gateway. */
class StoreCheckout
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly InvoiceService $invoices,
        private readonly CheckoutService $checkout,
    ) {}

    /** Allowed amounts of time to buy at once. */
    public function units(array $item): array
    {
        return match ($item['billing']) {
            'monthly' => [1, 3, 6, 12],
            'yearly' => [1, 2, 3],
            default => [1],
        };
    }

    /** @return array{price: float, months: ?int, label: string} */
    public function quote(array $item, int $units): array
    {
        $units = in_array($units, $this->units($item), true) ? $units : 1;

        return [
            'price' => round($item['price'] * $units, 2),
            'months' => match ($item['billing']) { 'monthly' => $units, 'yearly' => $units * 12, default => null },
            'label' => match ($item['billing']) {
                'one_time' => __('store.line_once', ['name' => $item['name']]),
                'monthly' => trans_choice('store.line_months', $units, ['name' => $item['name'], 'count' => $units]),
                default => trans_choice('store.line_years', $units, ['name' => $item['name'], 'count' => $units]),
            },
        ];
    }

    /**
     * @return array{invoice: Invoice, result: \App\Modules\Billing\Payments\CheckoutResult|null}
     *
     * @throws BillingException
     */
    public function start(Restaurant $restaurant, string $slug, int $units, string $gatewayCode): array
    {
        $item = $this->catalog->find($slug);

        if (! $item || ! $this->catalog->purchasable($item) || ! $item['visible']) {
            throw new BillingException(__('store.not_for_sale'));
        }

        $quote = $this->quote($item, $units);
        $invoice = $this->invoices->createForStore($restaurant, $quote['label'], $quote['price'], $item['currency'], $slug, $quote['months']);

        return $this->checkout->payInvoice($restaurant, $invoice, $gatewayCode);
    }
}
