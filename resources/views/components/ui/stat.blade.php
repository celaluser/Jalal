@props(['label', 'value', 'hero' => false, 'icon' => null, 'hint' => null])
@if ($hero)
    {{-- The one hero figure of a view: dark ink card with a quiet QR texture --}}
    <div {{ $attributes->class('relative flex flex-col justify-between gap-6 overflow-hidden rounded-2xl bg-ink-950 p-6 text-white shadow-card') }}>
        <x-ui.qr-pattern class="pointer-events-none absolute -end-6 -top-6 size-44 text-white/[0.06]" :seed="11" />
        <div class="relative"><p class="text-sm font-medium text-ink-300">{{ $label }}</p>
        <p class="display tnum mt-2 text-4xl font-semibold sm:text-5xl"><bdi>{{ $value }}</bdi></p></div>
        @if ($hint)<p class="relative max-w-[16rem] text-xs text-ink-400">{{ $hint }}</p>@endif
    </div>
@else
    <div {{ $attributes->class('card p-5') }}>
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-muted">{{ $label }}</p>
            @if ($icon)<span class="grid size-8 place-items-center rounded-lg bg-brand-100 text-brand-800 dark:bg-brand-900/40 dark:text-brand-200"><x-ui.icon :name="$icon" size="4" /></span>@endif
        </div>
        <p class="display tnum mt-3 text-3xl font-semibold"><bdi>{{ $value }}</bdi></p>
        @if ($hint)<p class="mt-1 text-xs text-muted">{{ $hint }}</p>@endif
    </div>
@endif
