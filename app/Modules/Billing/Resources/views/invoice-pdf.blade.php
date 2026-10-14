<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
    h1 { font-size: 22px; margin: 0 0 4px; }
    table { width: 100%; border-collapse: collapse; }
    .meta td { vertical-align: top; padding: 2px 0; }
    .items th { text-align: left; border-bottom: 1px solid #999; padding: 6px 0; }
    .items td { padding: 6px 0; border-bottom: 1px solid #eee; }
    .right { text-align: right; }
    .totals td { padding: 3px 0; }
    .grand td { font-weight: bold; font-size: 14px; border-top: 1px solid #222; padding-top: 6px; }
    .badge { display: inline-block; padding: 2px 8px; border: 1px solid #222; text-transform: uppercase; font-size: 10px; }
</style>
</head>
<body>
@php($b = $invoice->billing ?? [])
<table class="meta">
    <tr>
        <td><h1>{{ __('billing.invoice') }} {{ $invoice->number }}</h1>
            <span class="badge">{{ __('billing.status_'.$invoice->status) }}</span></td>
        <td class="right">{{ __('billing.issued') }}: {{ $invoice->issued_at->toDateString() }}<br>
            @if ($invoice->paid_at){{ __('billing.paid_on') }}: {{ $invoice->paid_at->toDateString() }}@else{{ __('billing.due') }}: {{ $invoice->due_at?->toDateString() }}@endif</td>
    </tr>
</table>
<br>
<table class="meta">
    <tr>
        <td><strong>{{ __('billing.from') }}</strong><br>{{ $b['seller']['name'] ?? '' }}<br>{!! nl2br(e($b['seller']['address'] ?? '')) !!}
            @if (! empty($b['seller']['tax_id']))<br>{{ __('billing.tax_id') }}: {{ $b['seller']['tax_id'] }}@endif</td>
        <td><strong>{{ __('billing.bill_to') }}</strong><br>{{ $b['buyer']['name'] ?? '' }}<br>{{ $b['buyer']['email'] ?? '' }}<br>{!! nl2br(e($b['buyer']['address'] ?? '')) !!}
            @if (! empty($b['buyer']['tax_id']))<br>{{ __('billing.tax_id') }}: {{ $b['buyer']['tax_id'] }}@endif</td>
    </tr>
</table>
<br>
<table class="items">
    <thead><tr><th>{{ __('billing.description') }}</th><th class="right">{{ __('billing.amount') }}</th></tr></thead>
    <tbody>
    @foreach ($invoice->items as $item)
        <tr><td>{{ $item['description'] }}</td><td class="right">{{ number_format((float) $item['amount'], 2) }} {{ $invoice->currency_code }}</td></tr>
    @endforeach
    </tbody>
</table>
<br>
<table class="totals" style="width: 50%; margin-left: 50%;">
    <tr><td>{{ __('billing.subtotal') }}</td><td class="right">{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
    @if ((float) $invoice->discount_amount > 0)
        <tr><td>{{ __('billing.discount') }} ({{ $invoice->coupon_code }})</td><td class="right">-{{ number_format((float) $invoice->discount_amount, 2) }}</td></tr>
    @endif
    @if ((float) $invoice->tax_rate > 0)
        <tr><td>{{ $invoice->tax_name }} ({{ rtrim(rtrim(number_format((float) $invoice->tax_rate, 2), '0'), '.') }}%)</td><td class="right">{{ number_format((float) $invoice->tax_amount, 2) }}</td></tr>
    @endif
    <tr class="grand"><td>{{ __('billing.total') }}</td><td class="right">{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency_code }}</td></tr>
</table>
</body>
</html>
