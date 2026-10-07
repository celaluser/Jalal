<x-layouts.base :title="__('maintenance.title')">
    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 text-center">
        <x-ui.qr-pattern class="pointer-events-none absolute inset-0 m-auto size-[44rem] max-w-none text-ink-900/[0.04] dark:text-white/[0.04]" :cells="29" :seed="17" />
        <div class="relative">
            <x-ui.qr-mark size="14" class="mx-auto" />
            <h1 class="display mt-8 text-4xl font-semibold sm:text-5xl">{{ __('maintenance.title') }}</h1>
            <p class="mx-auto mt-4 max-w-md text-lg text-muted">{{ $message }}</p>
        </div>
    </div>
</x-layouts.base>
