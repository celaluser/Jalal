<x-installer::layout :step="$step">
    <h2 class="mb-2 text-lg font-semibold">{{ __('installer.finished') }}</h2>
    <p class="mb-4 text-sm text-gray-500">{{ __('installer.finished_help') }}</p>
    <a href="{{ url('/login') }}" class="block rounded-lg bg-brand-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-brand-700">{{ __('installer.go_to_login') }}</a>
</x-installer::layout>
