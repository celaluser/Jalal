<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $dir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @php($metaDescription = $description ?? platform_setting('seo.meta_description'))
    @if ($metaDescription)<meta name="description" content="{{ $metaDescription }}">@endif
    <meta property="og:title" content="{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}">
    @if ($metaDescription)<meta property="og:description" content="{{ $metaDescription }}">@endif
    @if ($favicon = platform_setting('general.favicon'))<link rel="icon" href="{{ $favicon }}">@endif
    {{-- Apply the saved theme before first paint to avoid a flash. --}}
    <script>
        try {
            var t = localStorage.getItem('theme') || 'system';
            if (t === 'dark' || (t !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
        } catch (e) {}
    </script>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <x-brand-style />
    @livewireStyles
    @stack('head')
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    @if (config('demo.enabled'))
        <div class="bg-amber-100 px-4 py-1.5 text-center text-xs text-amber-900" role="note">{{ __('demo.banner') }}</div>
    @endif
    @if (session('impersonator_id'))
        <form method="POST" action="{{ route('impersonation.stop') }}" class="flex items-center justify-center gap-3 bg-indigo-600 px-4 py-1.5 text-sm text-white" role="note">
            @csrf
            <span>{{ __('admin.impersonate.banner', ['name' => auth()->user()?->name]) }}</span>
            <button class="rounded bg-white px-2 py-0.5 font-semibold text-indigo-700">{{ __('admin.impersonate.stop') }}</button>
        </form>
    @endif
    @error('demo')<div class="bg-red-600 px-4 py-2 text-center text-sm text-white" role="alert">{{ $message }}</div>@enderror
    {{ $slot }}
    <x-cookie-banner />
    @livewireScripts
    @stack('scripts')
</body>
</html>
