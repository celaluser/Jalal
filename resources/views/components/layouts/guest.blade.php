<x-layouts.base :title="$title ?? null">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <a href="{{ url('/') }}" class="mb-6 text-2xl font-bold text-brand-600">{{ config('app.name') }}</a>
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            {{ $slot }}
        </div>
        <div class="mt-6 flex items-center gap-4 text-sm text-gray-500" x-data="themeToggle">
            <x-ui.language-switcher />
            <button type="button" x-on:click="toggle()" class="hover:text-gray-900 dark:hover:text-gray-100">{{ __('ui.toggle_theme') }}</button>
        </div>
    </div>
</x-layouts.base>
