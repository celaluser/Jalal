@php($money = fn (int $c) => $restaurant->money($c / 100))
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $order->label() }}</title>
    <style>
        @page { size: 80mm auto; margin: 4mm; }
        * { box-sizing: border-box; }
        body { font: 13px/1.35 ui-monospace, Menlo, Consolas, monospace; width: 72mm; margin: 0 auto; color: #000; }
        h1 { font-size: 20px; margin: 0; text-align: center; }
        .c { text-align: center; } .row { display: flex; justify-content: space-between; gap: 8px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .big { font-size: 26px; font-weight: 700; text-align: center; margin: 4px 0; }
        .opt { padding-left: 14px; font-size: 12px; } .note { font-weight: 700; }
        @media screen { body { margin: 20px auto; padding: 12px; border: 1px solid #ccc; } }
    </style>
</head>
<body onload="window.print()">
    <h1>{{ $restaurant->name }}</h1>
    <p class="c">{{ __('orders.type_'.$order->type) }}@if ($order->table_name) · {{ table_label($order->table_name) }}@endif</p>
    <p class="big">{{ $order->label() }}</p>
    <p class="c">{{ $order->created_at->timezone($restaurant->timezone)->format('Y-m-d H:i') }}</p>
    @if ($order->customer_name || $order->customer_phone)<p class="c">{{ $order->customer_name }} {{ $order->customer_phone }}</p>@endif
    @if ($order->delivery_address)<p class="c">{{ $order->delivery_address }}</p>@endif
    <hr>
    @foreach ($order->items as $item)
        <div class="row"><span>{{ $item->qty }}× {{ $item->name }}</span><span>{{ $money($item->total_cents) }}</span></div>
        @if ($item->optionsLabel())<div class="opt">+ {{ $item->optionsLabel() }}</div>@endif
        @if ($item->note)<div class="opt note">“{{ $item->note }}”</div>@endif
    @endforeach
    <hr>
    @if ($order->note)<p class="note">{{ __('orders.note') }}: {{ $order->note }}</p><hr>@endif
    @if ($order->discount_cents)<div class="row"><span>{{ __('marketing.promo_discount') }} {{ $order->promo_code }}</span><span>-{{ $money($order->discount_cents) }}</span></div>@endif
    @if ($order->service_cents)<div class="row"><span>{{ __('orders.service') }}</span><span>{{ $money($order->service_cents) }}</span></div>@endif
    @if ($order->delivery_cents)<div class="row"><span>{{ __('orders.delivery_fee') }}</span><span>{{ $money($order->delivery_cents) }}</span></div>@endif
    @if ($order->tax_cents)<div class="row"><span>{{ __('orders.tax') }}</span><span>{{ $money($order->tax_cents) }}</span></div>@endif
    <div class="row" style="font-weight:700;font-size:16px"><span>{{ __('orders.total') }}</span><span>{{ $money($order->total_cents) }}</span></div>
    <p class="c">{{ $order->isPaid() ? __('orders.paid').' · '.__('orders.pay_'.$order->payment_method) : __('orders.unpaid') }}</p>
    @if ($order->tip_cents)<div class="row"><span>{{ __('orders.tip') }}</span><span>{{ $money($order->tip_cents) }}</span></div>@endif
    <div style="width:26mm;height:26mm;margin:6px auto 0" aria-hidden="true">{!! \App\Modules\Tables\Qr\QrCode::svg($restaurant->publicUrl('order/'.$order->token.'/receipt'), new \App\Modules\Tables\Qr\QrStyle('#000000', '#ffffff', 'square')) !!}</div>
    <p class="c" style="font-size:10px">{{ __('orders.receipt_scan') }}</p>
</body>
</html>
