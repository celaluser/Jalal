@props(['title' => null, 'heading' => null, 'subheading' => null, 'wide' => false])
<x-layouts.base :title="$title">
    <div class="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]">
        {{-- Brand panel: the QR texture is the one expressive element, everything else stays quiet --}}
        <aside class="relative hidden overflow-hidden bg-ink-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <x-ui.qr-pattern class="pointer-events-none absolute -bottom-24 -end-24 size-[34rem] text-white/[0.055]" :cells="29" :seed="5" />
            <div class="pointer-events-none absolute -start-32 -top-32 size-96 rounded-full bg-brand-500/20 blur-3xl"></div>
            <a href="{{ route('home') }}" class="relative flex items-center gap-3">
                <x-ui.qr-mark size="10" />
                <span class="display text-xl font-semibold">{{ config('app.name') }}</span>
            </a>
            <div class="relative max-w-md">
                <h2 class="display text-4xl font-semibold leading-[1.1] xl:text-5xl">{{ __('auth.panel_title') }}</h2>
                <p class="mt-4 text-lg text-ink-300">{{ __('auth.panel_text') }}</p>
                <ul class="mt-10 space-y-4 text-ink-200">
                    @foreach (['qr' => 'auth.point_qr', 'zap' => 'auth.point_orders', 'languages' => 'auth.point_languages'] as $icon => $key)
                        <li class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-xl bg-white/10 text-brand-400"><x-ui.icon :name="$icon" size="5" /></span>{{ __($key) }}</li>
                    @endforeach
                </ul>
            </div>
            <p class="relative text-sm text-ink-500">&copy; {{ now()->year }} {{ config('app.name') }}</p>
        </aside>

        <div class="flex flex-col px-5 py-8 sm:px-10">
            <div class="flex items-center justify-between">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 lg:invisible">
                    <x-ui.qr-mark size="8" /><span class="display font-semibold">{{ config('app.name') }}</span>
                </a>
                <div class="flex items-center gap-2"><x-ui.language-switcher /><x-ui.theme-toggle /></div>
            </div>
            <main class="rise mx-auto flex w-full flex-1 flex-col justify-center py-10 {{ $wide ? 'max-w-xl' : 'max-w-sm' }}">
                @if ($heading)
                    <h1 class="display text-3xl font-semibold">{{ $heading }}</h1>
                    @if ($subheading)<p class="mt-2 text-muted">{{ $subheading }}</p>@endif
                @endif
                <div class="{{ $heading ? 'mt-8' : '' }} space-y-5">
                    @if (session('status'))<x-ui.alert>{{ session('status') }}</x-ui.alert>@endif
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</x-layouts.base>
