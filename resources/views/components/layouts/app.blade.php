<x-layouts.base :title="$title ?? null">
    <div class="flex min-h-screen flex-col" x-data="themeToggle">
        <header class="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
            <a href="{{ route('dashboard') }}" class="font-bold text-brand-600">{{ config('app.name') }}</a>
            <div class="flex items-center gap-4 text-sm">
                <x-ui.language-switcher />
                <button type="button" x-on:click="toggle()" class="text-gray-500 hover:text-gray-900 dark:hover:text-gray-100">{{ __('ui.toggle_theme') }}</button>
 @can('support.manage')<a href="{{ route('support.index') }}" class="text-gray-500 hover:text-gray-900 dark:hover:text-gray-100">{{ __('support.title') }}</a>@endcan
                <a href="{{ route('two-factor.show') }}" class="text-gray-500 hover:text-gray-900 dark:hover:text-gray-100">{{ __('ui.security') }}</a>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="text-gray-500 hover:text-gray-900 dark:hover:text-gray-100">{{ __('ui.logout') }}</button>
                </form>
            </div>
        </header>
        <main class="mx-auto w-full max-w-5xl flex-1 space-y-4 p-4 sm:p-6">
            <x-announcements />
            {{ $slot }}
        </main>
    </div>
</x-layouts.base>
