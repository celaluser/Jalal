<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $restaurant->name }}</title><meta name="theme-color" content="{{ $restaurant->brandColor() }}">
    <style>
        body { margin: 0; min-height: 100vh; font-family: system-ui, sans-serif; background: {{ $restaurant->brandColor() }}; color: #fff; display: flex; justify-content: center }
        main { width: 100%; max-width: 26rem; padding: 3rem 1.25rem; text-align: center; box-sizing: border-box }
        h1 { font-size: 1.6rem; margin: 0 0 2rem } a.btn { display: block; margin: .75rem 0; padding: 1rem; border-radius: 1rem; background: #fff; color: #111; font-weight: 600; text-decoration: none }
        a.btn:focus-visible { outline: 3px solid #fff; outline-offset: 3px } a.primary { background: #111; color: #fff }
    </style>
</head>
<body><main>
    <h1>{{ $restaurant->name }}</h1>
    @foreach ($buttons as $b)
        <a class="btn {{ $b['key'] === 'menu' ? 'primary' : '' }}" href="{{ $b['url'] }}" @if (! str_starts_with($b['url'], 'tel:')) rel="noopener" @endif>{{ __('marketing.link_label_'.$b['key']) }}</a>
    @endforeach
</main></body>
</html>
