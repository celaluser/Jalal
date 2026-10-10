@props(['step'])
@php($steps = ['requirements', 'license', 'database', 'admin', 'finish'])
<x-layouts.base :title="__('installer.title')">
    <div class="relative min-h-screen overflow-hidden px-4 py-10 sm:py-16">
        <x-ui.qr-pattern class="pointer-events-none absolute -end-20 -top-20 size-[30rem] text-ink-900/[0.045] dark:text-white/[0.04]" :cells="29" :seed="13" />
        <div class="relative mx-auto max-w-2xl">
            <div class="mb-8 flex items-center justify-between">
                <span class="flex items-center gap-3"><x-ui.qr-mark size="10" /><span class="display text-xl font-semibold">{{ __('installer.title') }}</span></span>
                <div class="flex items-center gap-2"><x-ui.language-switcher /><x-ui.theme-toggle /></div>
            </div>

            <ol class="mb-6 grid grid-cols-5 gap-2" aria-label="{{ __('installer.steps') }}">
                @foreach ($steps as $i => $label)
                    @php($n = $i + 1)
                    <li @if ($step === $n) aria-current="step" @endif class="min-w-0">
                        <span @class(['block h-1.5 rounded-full', 'bg-brand-500' => $n <= $step, 'bg-line' => $n > $step])></span>
                        <span @class(['mt-2 flex items-center gap-1.5 text-xs font-medium', 'text-fg' => $n === $step, 'text-muted' => $n !== $step])>
                            <span @class(['grid size-5 shrink-0 place-items-center rounded-full text-[11px]', 'bg-brand-500 text-ink-950' => $n <= $step, 'bg-surface-2 text-muted' => $n > $step])>@if ($n < $step)<x-ui.icon name="check" size="3" />@else{{ $n }}@endif</span>
                            <span class="hidden truncate sm:inline">{{ __('installer.step_'.$label) }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>

            <x-ui.card class="rise">
                @if (session('status'))<x-ui.alert class="mb-5">{{ session('status') }}</x-ui.alert>@endif
                {{ $slot }}
            </x-ui.card>
        </div>
    </div>
</x-layouts.base>
