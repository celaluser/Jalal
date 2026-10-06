<x-layouts.base :title="__('installer.title')">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="mb-1 text-center text-2xl font-bold text-brand-600">{{ __('installer.title') }}</h1>
        <ol class="mb-6 mt-4 flex justify-center gap-2 text-xs" aria-label="{{ __('installer.steps') }}">
            @foreach (['requirements', 'license', 'database', 'admin', 'finish'] as $i => $label)
                <li @class(['rounded-full px-3 py-1', 'bg-brand-600 text-white' => $step === $i + 1, 'bg-gray-200 dark:bg-gray-800' => $step !== $i + 1])
                    @if ($step === $i + 1) aria-current="step" @endif>{{ $i + 1 }}. {{ __('installer.step_'.$label) }}</li>
            @endforeach
        </ol>
        <x-ui.card>
            @if (session('status'))<x-ui.alert>{{ session('status') }}</x-ui.alert>@endif
            {{ $slot }}
        </x-ui.card>
    </div>
</x-layouts.base>
