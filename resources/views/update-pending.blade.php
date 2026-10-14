<x-layouts.base :title="__('update_pending.title')" :noindex="true">
    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 text-center">
        <div class="relative">
            <x-ui.qr-mark size="14" class="mx-auto" />
            <h1 class="display mt-8 text-3xl font-semibold sm:text-4xl">{{ __('update_pending.title') }}</h1>
            <p class="mx-auto mt-4 max-w-md text-lg text-muted">{{ __('update_pending.text') }}</p>
            @if (! empty($showAdminHint))<p class="mx-auto mt-6 max-w-md rounded-xl bg-surface-2 p-4 text-sm">{{ __('update_pending.admin_hint') }}</p>@endif
        </div>
    </div>
</x-layouts.base>
