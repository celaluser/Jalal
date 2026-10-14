<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $dir ?? 'ltr' }}" @if (! empty($tablet)) data-tablet style="font-size:112.5%" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $brandName = \App\Modules\Core\Support\Brand::name();
        $pageTitle = isset($title) && $title !== '' ? $title.' · '.$brandName : (platform_setting('seo.meta_title') ?: trim($brandName.(\App\Modules\Core\Support\Brand::tagline() ? ' — '.\App\Modules\Core\Support\Brand::tagline() : '')));
        $metaDescription = ($description ?? null) ?: (platform_setting('seo.meta_description') ?: (\App\Modules\Core\Support\Brand::tagline() ?: $brandName));
    @endphp
    <title>{{ $pageTitle }}</title>
    @if ($metaDescription)<meta name="description" content="{{ $metaDescription }}">@endif
    <x-seo-head :title="$pageTitle" :description="$metaDescription" :noindex="$noindex ?? false" :image="$image ?? null" :type="$ogType ?? 'website'" />
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
    <x-font-preload />
    <x-brand-style />
    @stack('head')
    @if ($customCss = platform_setting('general.custom_css'))<style>{!! str_ireplace('</style', '', $customCss) !!}</style>@endif
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
    @if (! empty($tablet))
        {{-- Tablet mode: keep the screen on while this page is open (browsers that support it). --}}
        <script>
            (function () {
                var lock = null;
                function acquire() { if ('wakeLock' in navigator && !document.hidden) { navigator.wakeLock.request('screen').then(function (l) { lock = l; }).catch(function () {}); } }
                document.addEventListener('visibilitychange', acquire); acquire();
            })();
        </script>
    @endif
    <x-cookie-banner />
    @stack('scripts')
    @if ($customJs = platform_setting('general.custom_js'))<script>{!! str_ireplace('</script', '<\/script', $customJs) !!}</script>@endif
</body>
</html>
