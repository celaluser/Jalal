<x-installer::layout :step="$step">
    <h2 class="display text-2xl font-semibold">{{ __('installer.step_requirements') }}</h2>
    <p class="mt-1 text-sm text-muted">{{ __('installer.requirements_help') }}</p>
    <ul class="mt-5 divide-y divide-line overflow-hidden rounded-xl border border-line text-sm">
        @foreach ($checks as $check)
            <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                <span>{{ $check['label'] }}</span>
                @if ($check['ok'])<span class="flex items-center gap-1.5 font-medium text-accent-700 dark:text-accent-300"><x-ui.icon name="check-circle" size="4" />{{ $check['detail'] }}</span>
                @else<span class="flex items-center gap-1.5 font-medium text-red-600 dark:text-red-400"><x-ui.icon name="alert" size="4" />{{ $check['detail'] }}</span>@endif
            </li>
        @endforeach
    </ul>
    <div class="mt-6">
        @if ($passes)
            <a href="{{ route('install.license') }}" class="btn btn-primary btn-lg w-full">{{ __('installer.next') }}<x-ui.icon name="arrow-right" size="5" class="rtl:rotate-180" /></a>
        @else
            <x-ui.alert type="error" class="mb-4">{{ __('installer.requirements_failed') }}</x-ui.alert>
            <a href="{{ route('install.welcome') }}" class="btn btn-secondary w-full"><x-ui.icon name="refresh" size="4" />{{ __('installer.recheck') }}</a>
        @endif
    </div>
</x-installer::layout>
