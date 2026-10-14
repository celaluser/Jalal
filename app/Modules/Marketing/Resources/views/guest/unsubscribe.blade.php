<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>{{ __('marketing.unsub_title') }}</title>
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f4f2;color:#1b1b18;font:16px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;padding:16px}
        main{max-width:26rem;width:100%;background:#fff;border-radius:20px;padding:32px 28px;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,.08)}
        h1{font-size:1.35rem;margin:0 0 8px} p{color:#5c5c55;margin:0 0 20px}
        button{font:inherit;font-weight:600;border:0;border-radius:12px;padding:12px 22px;background:#1b1b18;color:#fff;cursor:pointer;width:100%}
        @media (prefers-color-scheme:dark){body{background:#121210;color:#f2f2ee}main{background:#1c1c19}p{color:#a8a89f}button{background:#fff;color:#1b1b18}}
    </style>
</head>
<body>
    <main>
        @if ($done)
            <h1>{{ __('marketing.unsub_done') }}</h1>
            <p style="margin:0">{{ $confirmed ? __('marketing.unsub_done_text', ['name' => $restaurant->name]) : __('marketing.unsub_already', ['name' => $restaurant->name]) }}</p>
        @else
            <h1>{{ __('marketing.unsub_ask', ['name' => $restaurant->name]) }}</h1>
            <p>{{ __('marketing.unsub_ask_text') }}</p>
            <form method="POST" action="{{ $url }}"><button type="submit">{{ __('marketing.unsub_button') }}</button></form>
        @endif
    </main>
</body>
</html>
