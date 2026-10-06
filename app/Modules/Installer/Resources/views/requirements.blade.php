<x-installer::layout :step="$step">
    <h2 class="mb-3 text-lg font-semibold">{{ __('installer.step_requirements') }}</h2>
    <ul class="mb-5 divide-y divide-gray-100 text-sm dark:divide-gray-800">
        @foreach ($checks as $check)
            <li class="flex justify-between py-1.5">
                <span>{{ $check['label'] }}</span>
                <span @class(['font-medium', 'text-green-600' => $check['ok'], 'text-red-600' => ! $check['ok']])>{{ $check['ok'] ? '✓' : '✗' }} {{ $check['detail'] }}</span>
            </li>
        @endforeach
    </ul>
    @if ($passes)
        <a href="{{ route('install.license') }}" class="block rounded-lg bg-brand-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-brand-700">{{ __('installer.next') }}</a>
    @else
        <x-ui.alert type="error">{{ __('installer.requirements_failed') }}</x-ui.alert>
        <a href="{{ route('install.welcome') }}" class="block rounded-lg bg-gray-100 px-4 py-2.5 text-center text-sm font-semibold dark:bg-gray-800">{{ __('installer.recheck') }}</a>
    @endif
</x-installer::layout>
