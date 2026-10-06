@props(['title' => null, 'description' => null])
@php
    use App\Modules\Cms\Models\Page;

    $locale = app()->getLocale();
    $footerPages = Page::published()->where('in_footer', true)->where('locale', $locale)->orderBy('sort')->get();
    if ($footerPages->isEmpty() && $locale !== config('app.default_locale')) {
        $footerPages = Page::published()->where('in_footer', true)->where('locale', config('app.default_locale'))->orderBy('sort')->get();
    }
    $logo = platform_setting('general.logo');
    $registration = (bool) platform_setting('auth.registration_enabled', '1');
@endphp
<x-layouts.base :title="$title" :description="$description">
    <div class="flex min-h-screen flex-col" x-data="themeToggle">
        <header class="sticky top-0 z-30 border-b border-gray-200 bg-white/90 backdrop-blur dark:border-gray-800 dark:bg-gray-950/90">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold text-brand-600">
                    @if ($logo)<img src="{{ $logo }}" alt="" class="h-8 w-auto">@endif
                    <span>{{ config('app.name') }}</span>
                </a>
                <nav class="hidden items-center gap-6 text-sm md:flex" aria-label="{{ __('site.nav.label') }}">
                    <a href="{{ route('home') }}#features" class="hover:text-brand-600">{{ __('site.nav.features') }}</a>
                    <a href="{{ route('home') }}#pricing" class="hover:text-brand-600">{{ __('site.nav.pricing') }}</a>
                    <a href="{{ route('home') }}#faq" class="hover:text-brand-600">{{ __('site.nav.faq') }}</a>
                    <a href="{{ route('blog.index') }}" class="hover:text-brand-600">{{ __('site.nav.blog') }}</a>
                </nav>
                <div class="flex items-center gap-3 text-sm">
                    <x-ui.language-switcher />
                    <button type="button" x-on:click="toggle()" class="text-gray-500 hover:text-gray-900 dark:hover:text-gray-100" aria-label="{{ __('ui.toggle_theme') }}">◐</button>
                    @auth
                        <a href="{{ auth()->user()->isPlatformUser() ? route('admin.dashboard') : route('dashboard') }}" class="rounded-lg bg-brand-600 px-3 py-1.5 font-semibold text-white hover:bg-brand-700">{{ __('ui.dashboard') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="hover:text-brand-600">{{ __('auth.login') }}</a>
                        @if ($registration)<a href="{{ route('register') }}" class="rounded-lg bg-brand-600 px-3 py-1.5 font-semibold text-white hover:bg-brand-700">{{ __('site.nav.start') }}</a>@endif
                    @endauth
                </div>
            </div>
        </header>
        <main class="flex-1">{{ $slot }}</main>
        <footer class="border-t border-gray-200 bg-white py-8 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-950">
            <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ now()->year }} {{ config('app.name') }}</p>
                <nav class="flex flex-wrap gap-4" aria-label="{{ __('site.nav.footer') }}">
                    @foreach ($footerPages as $page)<a href="{{ route('pages.show', $page->slug) }}" class="hover:text-brand-600">{{ $page->title }}</a>@endforeach
                    <a href="{{ route('blog.index') }}" class="hover:text-brand-600">{{ __('site.nav.blog') }}</a>
                </nav>
            </div>
        </footer>
    </div>
</x-layouts.base>
