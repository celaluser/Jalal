<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('orders.receipt') }} {{ $order->label() }} · {{ $restaurant->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font: 13px/1.4 {{ $pdf ? 'DejaVu Sans, sans-serif' : 'ui-monospace, Menlo, Consolas, monospace' }}; color: #000; margin: 0; padding: {{ $pdf ? '4mm' : '24px 12px' }}; background: {{ $pdf ? '#fff' : '#f4f4f5' }}; }
        .paper { max-width: 340px; margin: 0 auto; background: #fff; padding: {{ $pdf ? '0' : '18px 16px' }}; {{ $pdf ? '' : 'border-radius: 8px; box-shadow: 0 1px 6px rgba(0,0,0,.12);' }} }
        h1 { font-size: 18px; margin: 0; text-align: center; } .c { text-align: center; margin: 2px 0; } .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; } td { padding: 2px 0; vertical-align: top; } td.r { text-align: right; white-space: nowrap; }
        hr { border: 0; border-top: 1px dashed #000; margin: 8px 0; } .total td { font-weight: 700; font-size: 15px; } .opt { padding-left: 12px; font-size: 11px; color: #444; }
        .qr { width: 110px; height: 110px; margin: 8px auto 0; display: block; } .qr svg { width: 100%; height: 100%; }
        .actions { max-width: 340px; margin: 14px auto 0; display: flex; gap: 8px; justify-content: center; }
        .actions a, .actions button { font: 600 13px system-ui, sans-serif; padding: 9px 14px; border-radius: 8px; border: 1px solid #bbb; background: #fff; color: #000; text-decoration: none; cursor: pointer; }
        @media print { .actions { display: none; } body { background: #fff; padding: 0; } .paper { box-shadow: none; } }
    </style>
</head>
<body>
<div class="paper">
    <h1>{{ $restaurant->name }}</h1>
    @if ($restaurant->address)<p class="c muted">{{ $restaurant->address }}</p>@endif
    <p class="c">{{ __('orders.receipt') }} {{ $order->label() }}</p>
    <p class="c muted">{{ $order->created_at->timezone($tz)->format('Y-m-d H:i') }} · {{ __('orders.type_'.$order->type) }}@if ($order->table_name) · {{ table_label($order->table_name) }}@endif</p>
    <hr>
    <table>
        @foreach ($order->items as $item)
            <tr><td>{{ $item->qty }}× {{ $item->name }}</td><td class="r">{{ $money($item->total_cents) }}</td></tr>
            @if ($item->optionsLabel())<tr><td colspan="2" class="opt">+ {{ $item->optionsLabel() }}</td></tr>@endif
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td>{{ __('orders.subtotal') }}</td><td class="r">{{ $money($order->subtotal_cents) }}</td></tr>
        @if ($order->discount_cents)<tr><td>{{ __('marketing.promo_discount') }} {{ $order->promo_code }}</td><td class="r">−{{ $money($order->discount_cents) }}</td></tr>@endif
        @if ($order->service_cents)<tr><td>{{ __('orders.service') }}</td><td class="r">{{ $money($order->service_cents) }}</td></tr>@endif
        @if ($order->packaging_cents)<tr><td>{{ __('orders.packaging') }}</td><td class="r">{{ $money($order->packaging_cents) }}</td></tr>@endif
        @if ($order->delivery_cents)<tr><td>{{ __('orders.delivery_fee') }}</td><td class="r">{{ $money($order->delivery_cents) }}</td></tr>@endif
        @if ($order->tax_cents)<tr><td>{{ __('orders.tax') }}</td><td class="r">{{ $money($order->tax_cents) }}</td></tr>@endif
        <tr class="total"><td>{{ __('orders.total') }}</td><td class="r">{{ $money($order->total_cents) }}</td></tr>
        @if ($order->tip_cents)<tr><td>{{ __('orders.tip') }}</td><td class="r">{{ $money($order->tip_cents) }}</td></tr>@endif
    </table>
    @php($paid = $order->payments->where('status', 'paid'))
    @if ($paid->isNotEmpty())
        <hr>
        <table>
            @foreach ($paid as $p)
                <tr><td>{{ __('orders.pay_'.$p->method) }}</td><td class="r">{{ $money($p->amount_cents + $p->tip_cents) }}</td></tr>
                @if ($p->refunded_cents)<tr><td class="opt">{{ __('orders.refunded') }}</td><td class="r">−{{ $money($p->refunded_cents) }}</td></tr>@endif
            @endforeach
        </table>
    @endif
    <p class="c" style="margin-top:8px;font-weight:700">{{ $order->isPaid() ? __('orders.paid') : __('orders.unpaid') }}</p>
    <p class="c muted">{{ __('orders.receipt_thanks') }}</p>
    @if ($pdf)<img class="qr" src="{{ $qr }}" alt="">@else<div class="qr" aria-hidden="true">{!! $qr !!}</div>@endif
</div>
@unless ($pdf)
    <div class="actions">
        <button type="button" onclick="window.print()">{{ __('orders.print') }}</button>
        <a href="{{ $url }}.pdf">PDF</a>
    </div>
@endunless
</body>
</html>
