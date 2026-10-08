<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $restaurant->name }}</title>
    <style>
        @page { size: A5; margin: 0 }
        body { margin: 0; font-family: system-ui, sans-serif; color: #111; background: #eee }
        .sheet { width: 148mm; height: 210mm; margin: 12px auto; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 14mm; box-sizing: border-box; border-top: 10mm solid {{ $restaurant->brandColor() }} }
        h1 { font-size: 30pt; margin: 0 0 4mm; line-height: 1.1 } h2 { font-size: 15pt; font-weight: 500; margin: 0 0 10mm; color: #444 }
        .qr { width: 80mm; height: 80mm } .qr svg { width: 100%; height: 100% } p { font-size: 11pt; margin: 8mm 0 0; color: #333; word-break: break-all }
        @media print { body { background: #fff } .sheet { margin: 0 } .bar { display: none } }
        .bar { text-align: center; padding: 10px } button { padding: 8px 18px; font-size: 14px }
    </style>
</head>
<body>
    <div class="bar"><button onclick="window.print()">{{ __('marketing.flyer_print') }}</button></div>
    <div class="sheet">
        <h1>{{ $headline ?: $restaurant->name }}</h1>
        <h2>{{ __('marketing.flyer_scan') }}</h2>
        <div class="qr">{!! $qr !!}</div>
        <p dir="ltr">{{ $url }}</p>
    </div>
</body>
</html>
