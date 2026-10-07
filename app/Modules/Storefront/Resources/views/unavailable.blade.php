<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $restaurant->name }}</title>
    <style>{!! $themeCss !!}</style>
    @vite(['resources/css/app.css'])
</head>
<body class="menu-page grid min-h-screen place-items-center p-6 text-center">
    <main class="max-w-sm">
        <h1 class="text-2xl font-semibold">{{ $restaurant->name }}</h1>
        <p class="mt-4 text-lg font-medium">{{ __('customer.unavailable_title') }}</p>
        <p class="menu-muted mt-2">{{ __('customer.unavailable_text') }}</p>
    </main>
</body>
</html>
