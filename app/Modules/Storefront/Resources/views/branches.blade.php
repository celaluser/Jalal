<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $restaurant->name }} · {{ __('branches.choose_title') }}</title>
    <style>{!! $themeCss !!}</style>
    @vite(['resources/css/app.css'])
</head>
<body class="menu-page min-h-screen p-6">
    <main class="mx-auto max-w-md pt-10">
        <h1 class="text-2xl font-semibold">{{ $restaurant->name }}</h1>
        <p class="mt-3 text-lg font-medium">{{ __('branches.choose_title') }}</p>
        <p class="menu-muted mt-1">{{ __('branches.choose_text') }}</p>
        <ul class="mt-6 grid gap-3">
            @foreach ($branches as $branch)
                <li><a href="{{ $base }}?branch={{ $branch->slug }}" class="block rounded-2xl border border-current/15 p-4 transition hover:border-current/40">
                    <span class="block font-semibold">{{ $branch->name }}</span>
                    <span class="menu-muted block text-sm">{{ collect([$branch->address, $branch->city])->filter()->implode(', ') }}</span>
                    @unless ($branch->isOpen())<span class="menu-muted mt-1 block text-xs">{{ __('branches.closed_now') }}</span>@endunless
                </a></li>
            @endforeach
        </ul>
    </main>
</body>
</html>
