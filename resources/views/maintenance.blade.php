<x-layouts.base :title="__('maintenance.title')">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 text-center">
        <h1 class="mb-3 text-3xl font-bold text-brand-600">{{ __('maintenance.title') }}</h1>
        <p class="max-w-md text-gray-600 dark:text-gray-300">{{ $message }}</p>
    </div>
</x-layouts.base>
