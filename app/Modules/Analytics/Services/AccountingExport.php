<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;

/**
 * Sales for the accountant: one document per order, or one per day, written as a CSV the accounting
 * package can import (Xero sales invoices, QuickBooks Online invoices) or as a plain spreadsheet.
 * Cancelled orders are left out; amounts are net of tax with the tax shown beside them.
 */
class AccountingExport
{
    public const FORMATS = ['generic', 'xero', 'quickbooks'];

    public const MODES = ['orders', 'daily'];

    /** @return array{0: list<string>, 1: \Generator<int, list<string|int|float>>} header and rows */
    public function build(Restaurant $restaurant, CarbonImmutable $from, CarbonImmutable $to, string $format, string $mode, string $account = '200', string $taxLabel = 'Tax on Sales'): array
    {
        $format = in_array($format, self::FORMATS, true) ? $format : 'generic';
        $docs = $this->documents($restaurant, $from, $to, $mode === 'daily' ? 'daily' : 'orders');

        return match ($format) {
            'xero' => [
                ['*ContactName', 'EmailAddress', '*InvoiceNumber', 'Reference', '*InvoiceDate', '*DueDate', '*Description', '*Quantity', '*UnitAmount', '*AccountCode', '*TaxType', 'Currency'],
                (function () use ($docs, $account, $taxLabel) {
                    foreach ($docs as $d) {
                        yield [$d['contact'], '', $d['number'], $d['reference'], $d['date']->format('d/m/Y'), $d['date']->format('d/m/Y'), $d['description'], 1, $this->money($d['net']), $account, $d['tax'] > 0 ? $taxLabel : 'Tax Exempt', $d['currency']];
                    }
                })(),
            ],
            'quickbooks' => [
                ['InvoiceNo', 'Customer', 'InvoiceDate', 'DueDate', 'Memo', 'Item(Product/Service)', 'ItemDescription', 'ItemQuantity', 'ItemRate', 'ItemAmount', 'ItemTaxCode', 'ItemTaxAmount', 'Currency'],
                (function () use ($docs, $taxLabel) {
                    foreach ($docs as $d) {
                        yield [$d['number'], $d['contact'], $d['date']->format('m/d/Y'), $d['date']->format('m/d/Y'), $d['reference'], 'Sales', $d['description'], 1, $this->money($d['net']), $this->money($d['net']), $d['tax'] > 0 ? $taxLabel : 'Non', $this->money($d['tax']), $d['currency']];
                    }
                })(),
            ],
            default => [
                ['Date', 'Number', 'Description', 'Customer', 'Net', 'Tax', 'Total', 'Tips', 'Payment', 'Orders', 'Currency'],
                (function () use ($docs) {
                    foreach ($docs as $d) {
                        yield [$d['date']->format('Y-m-d'), $d['number'], $d['description'], $d['contact'], $this->money($d['net']), $this->money($d['tax']), $this->money($d['net'] + $d['tax']), $this->money($d['tips']), $d['payment'], $d['orders'], $d['currency']];
                    }
                })(),
            ],
        };
    }

    /** @return \Generator<int, array<string, mixed>> */
    private function documents(Restaurant $restaurant, CarbonImmutable $from, CarbonImmutable $to, string $mode): \Generator
    {
        $tz = in_array($restaurant->timezone, timezone_identifiers_list(), true) ? $restaurant->timezone : 'UTC';
        $currency = $restaurant->currency_code ?: 'USD';
        $query = Order::query()->where('status', '!=', OrderStatus::CANCELLED)
            ->where('created_at', '>=', $from->startOfDay()->utc())->where('created_at', '<=', $to->endOfDay()->utc())->orderBy('id');

        if ($mode === 'orders') {
            foreach ($query->cursor() as $o) {
                yield [
                    'number' => 'ORD-'.$o->number, 'date' => $o->created_at->setTimezone($tz), 'contact' => $o->customer_name ?: __('analytics.walk_in'),
                    'reference' => $restaurant->name, 'description' => __('analytics.sales_desc', ['number' => $o->number]),
                    'net' => max(0, (int) $o->total_cents - (int) $o->tax_cents), 'tax' => (int) $o->tax_cents, 'tips' => (int) $o->tip_cents,
                    'payment' => (string) $o->payment_method, 'orders' => 1, 'currency' => $o->currency_code ?: $currency,
                ];
            }

            return;
        }

        $days = [];

        foreach ($query->cursor() as $o) {
            $day = $o->created_at->setTimezone($tz)->startOfDay();
            $key = $day->format('Ymd');
            $days[$key] ??= ['date' => $day, 'net' => 0, 'tax' => 0, 'tips' => 0, 'orders' => 0];
            $days[$key]['net'] += max(0, (int) $o->total_cents - (int) $o->tax_cents);
            $days[$key]['tax'] += (int) $o->tax_cents;
            $days[$key]['tips'] += (int) $o->tip_cents;
            $days[$key]['orders']++;
        }

        ksort($days);

        foreach ($days as $key => $d) {
            yield $d + [
                'number' => 'SALES-'.$key, 'contact' => __('analytics.daily_sales'), 'reference' => $restaurant->name,
                'description' => __('analytics.sales_day', ['date' => $d['date']->format('Y-m-d'), 'count' => $d['orders']]), 'payment' => '', 'currency' => $currency,
            ];
        }
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
