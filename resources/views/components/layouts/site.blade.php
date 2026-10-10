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
    $links = [
        route('home').'#features' => __('site.nav.features'),
        route('home').'#pricing' => __('site.nav.pricing'),
        route('home').'#faq' => __('site.nav.faq'),
        route('blog.index') => __('site.nav.blog'),
    ];
@endphp
<x-layouts.base :title="$title" :description="$description">
    <div class="flex min-h-screen flex-col">
        <header class="sticky top-0 z-40 border-b border-line/70 bg-surface/80 backdrop-blur-xl" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    @if ($logo)<img src="{{ $logo }}" alt="" class="h-8 w-auto">@else<x-ui.qr-mark size="8" />@endif
                    <span class="display text-lg font-semibold">{{ config('app.name') }}</span>
                </a>
                <nav class="hidden items-center gap-1 text-sm font-medium lg:flex" aria-label="{{ __('site.nav.label') }}">
                    @foreach ($links as $url => $label)<a href="{{ $url }}" class="rounded-lg px-3 py-2 text-muted transition hover:bg-surface-2 hover:text-fg">{{ $label }}</a>@endforeach
                </nav>
                <div class="flex items-center gap-2">
                    <span class="hidden sm:block"><x-ui.language-switcher /></span>
                    <x-ui.theme-toggle />
                    @auth
                        <a href="{{ auth()->user()->isPlatformUser() ? route('admin.dashboard') : route('dashboard') }}" class="btn btn-primary btn-sm">{{ __('ui.dashboard') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-ghost btn-sm hidden sm:inline-flex">{{ __('auth.login') }}</a>
                        @if ($registration)<a href="{{ route('register') }}" class="btn btn-primary btn-sm hidden sm:inline-flex">{{ __('site.nav.start') }}</a>@endif
                    @endauth
                    <button type="button" class="grid size-9 place-items-center rounded-lg text-muted hover:bg-surface-2 lg:hidden" x-on:click="open = !open" :aria-expanded="open" aria-controls="mobile-nav" aria-label="{{ __('ui.menu') }}"><x-ui.icon name="menu" x-show="!open" /><x-ui.icon name="x" x-show="open" x-cloak /></button>
                </div>
            </div>
            <div id="mobile-nav" x-show="open" x-cloak x-transition class="border-t border-line bg-surface px-4 pb-5 pt-3 lg:hidden">
                <nav class="flex flex-col text-base font-medium" aria-label="{{ __('site.nav.label') }}">
                    @foreach ($links as $url => $label)<a href="{{ $url }}" x-on:click="open = false" class="rounded-lg px-3 py-3 hover:bg-surface-2">{{ $label }}</a>@endforeach
                </nav>
                @guest
                    <div class="mt-3 grid gap-2"><a href="{{ route('login') }}" class="btn btn-secondary">{{ __('auth.login') }}</a>@if ($registration)<a href="{{ route('register') }}" class="btn btn-primary">{{ __('site.nav.start') }}</a>@endif</div>
                @endguest
                <div class="mt-4"><x-ui.language-switcher /></div>
            </div>
        </header>

        <main class="flex-1">{{ $slot }}</main>

        <footer class="relative overflow-hidden bg-ink-950 text-ink-300">
            <x-ui.qr-pattern class="pointer-events-none absolute -bottom-32 -end-16 size-[26rem] text-white/[0.04]" :cells="29" :seed="21" />
            <div class="relative mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-[1.4fr_1fr_1fr_1fr]">
                <div>
                    <a href="{{ route('home') }}" class="flex items-center gap-2.5"><x-ui.qr-mark size="9" /><span class="display text-lg font-semibold text-white">{{ config('app.name') }}</span></a>
                    <p class="mt-4 max-w-xs text-sm text-ink-400">{{ __('site.footer.tagline') }}</p>
                </div>
                <nav aria-label="{{ __('site.footer.product') }}"><p class="eyebrow !text-ink-500">{{ __('site.footer.product') }}</p>
                    <ul class="mt-4 space-y-2.5 text-sm"><li><a class="hover:text-white" href="{{ route('home') }}#features">{{ __('site.nav.features') }}</a></li><li><a class="hover:text-white" href="{{ route('home') }}#pricing">{{ __('site.nav.pricing') }}</a></li><li><a class="hover:text-white" href="{{ route('home') }}#faq">{{ __('site.nav.faq') }}</a></li></ul></nav>
                <nav aria-label="{{ __('site.footer.resources') }}"><p class="eyebrow !text-ink-500">{{ __('site.footer.resources') }}</p>
                    <ul class="mt-4 space-y-2.5 text-sm"><li><a class="hover:text-white" href="{{ route('blog.index') }}">{{ __('site.nav.blog') }}</a></li><li><a class="hover:text-white" href="{{ route('login') }}">{{ __('auth.login') }}</a></li></ul></nav>
                <nav aria-label="{{ __('site.nav.footer') }}"><p class="eyebrow !text-ink-500">{{ __('site.footer.legal') }}</p>
                    <ul class="mt-4 space-y-2.5 text-sm">@forelse ($footerPages as $page)<li><a class="hover:text-white" href="{{ route('pages.show', $page->slug) }}">{{ $page->title }}</a></li>@empty<li class="text-ink-500">—</li>@endforelse</ul></nav>
            </div>
            <div class="relative border-t border-white/10"><div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-xs text-ink-500 sm:px-6"><p>&copy; {{ now()->year }} {{ config('app.name') }}</p><x-ui.language-switcher /></div></div>
        </footer>
    </div>
</x-layouts.base>
