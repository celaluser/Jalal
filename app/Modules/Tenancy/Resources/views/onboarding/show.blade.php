<x-layouts.app :title="__('onboarding.title')">
    @php($steps = [1 => __('onboarding.step_profile'), 2 => __('onboarding.step_branding'), 3 => __('onboarding.step_done')])
    <div class="mx-auto max-w-3xl">
        <x-ui.page-header :title="__('onboarding.title')" :description="__('onboarding.subtitle')" />

        {{-- Progress: the current step is named, finished steps show a check --}}
        <ol class="mb-6 flex items-center gap-2" aria-label="{{ __('onboarding.progress') }}">
            @foreach ($steps as $n => $label)
                <li class="flex flex-1 items-center gap-2" @if ($n === $step) aria-current="step" @endif>
                    <span @class(['grid size-8 shrink-0 place-items-center rounded-full text-sm font-semibold', 'bg-brand-500 text-ink-950' => $n === $step, 'bg-accent-600 text-white' => $n < $step, 'bg-surface-2 text-muted' => $n > $step])>
                        @if ($n < $step)<x-ui.icon name="check" size="4" />@else{{ $n }}@endif
                    </span>
                    <span @class(['hidden text-sm font-medium sm:inline', 'text-muted' => $n !== $step])>{{ $label }}</span>
                    @if ($n < 3)<span class="h-px flex-1 bg-line"></span>@endif
                </li>
            @endforeach
        </ol>

        @if ($step === 1)
            <x-ui.card :title="__('onboarding.step_profile')" :description="__('onboarding.profile_help')">
                <form method="POST" action="{{ route('onboarding.profile') }}" class="space-y-4">
                    @csrf
                    @include('tenancy::partials-profile')
                    <div class="flex justify-end pt-2"><x-ui.button>{{ __('onboarding.continue') }}<x-ui.icon name="arrow-right" size="4" class="rtl:rotate-180" /></x-ui.button></div>
                </form>
            </x-ui.card>
        @elseif ($step === 2)
            <x-ui.card :title="__('onboarding.step_branding')" :description="__('onboarding.branding_help')">
                <form method="POST" action="{{ route('onboarding.branding') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    @include('tenancy::partials-branding')
                    <div class="flex items-center justify-between pt-2">
                        <a href="{{ route('onboarding.show', ['step' => 1]) }}" class="btn btn-ghost">{{ __('onboarding.back') }}</a>
                        <x-ui.button>{{ __('onboarding.continue') }}<x-ui.icon name="arrow-right" size="4" class="rtl:rotate-180" /></x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @else
            <div class="relative overflow-hidden rounded-2xl bg-ink-950 p-8 text-white shadow-card sm:p-10" x-data="{ copied: false }">
                <x-ui.qr-pattern class="pointer-events-none absolute -end-8 -top-8 size-56 text-white/[0.07]" :seed="5" />
                <h2 class="display relative text-3xl font-semibold">{{ __('onboarding.ready_title') }}</h2>
                <p class="relative mt-2 max-w-md text-ink-300">{{ __('onboarding.ready_text') }}</p>
                <p class="display relative mt-6 break-all text-xl font-semibold text-brand-400" dir="ltr">{{ $menuUrl }}</p>
                <div class="relative mt-6 flex flex-wrap gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" x-on:click="navigator.clipboard.writeText(@js($menuUrl)).then(() => { copied = true; setTimeout(() => copied = false, 1800) })"><span x-text="copied ? @js(__('tenancy.copied')) : @js(__('tenancy.copy_link'))"></span></button>
                </div>
            </div>
            <div class="card card-pad mt-5">
                <h3 class="font-semibold">{{ __('onboarding.next_title') }}</h3>
                <ul class="mt-3 space-y-2 text-sm text-muted">
                    <li class="flex gap-2"><x-ui.icon name="check-circle" size="5" class="shrink-0 text-accent-600" />{{ __('onboarding.next_menu') }}</li>
                    <li class="flex gap-2"><x-ui.icon name="check-circle" size="5" class="shrink-0 text-accent-600" />{{ __('onboarding.next_tables') }}</li>
                    <li class="flex gap-2"><x-ui.icon name="check-circle" size="5" class="shrink-0 text-accent-600" />{{ $plan ? __('onboarding.next_plan', ['plan' => $plan->name]) : __('onboarding.next_no_plan') }}</li>
                </ul>
                <form method="POST" action="{{ route('onboarding.finish') }}" class="mt-6 flex justify-between">@csrf
                    <a href="{{ route('onboarding.show', ['step' => 2]) }}" class="btn btn-ghost">{{ __('onboarding.back') }}</a>
                    <x-ui.button size="lg">{{ __('onboarding.go_dashboard') }}</x-ui.button>
                </form>
            </div>
        @endif

        @if ($step < 3)
            <form method="POST" action="{{ route('onboarding.finish') }}" class="mt-4 text-center">@csrf
                <button class="text-sm text-muted underline-offset-2 hover:underline">{{ __('onboarding.skip') }}</button>
            </form>
        @endif
    </div>
</x-layouts.app>
