<x-installer::layout :step="$step">
    <div class="py-6 text-center">
        <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-accent-50 text-accent-700 dark:bg-accent-900/40 dark:text-accent-200"><x-ui.icon name="check-circle" size="8" /></span>
        <h2 class="display mt-5 text-2xl font-semibold">{{ __('installer.finished') }}</h2>
        <p class="mx-auto mt-2 max-w-sm text-sm text-muted">{{ __('installer.finished_help') }}</p>
        <div class="mx-auto mt-6 max-w-md rounded-xl border border-line p-4 text-start text-sm">
            <p class="font-medium">{{ __('installer.cron_title') }}</p>
            <p class="mt-1 text-muted">{{ __('installer.cron_help') }}</p>
            <code class="mt-2 block break-all rounded bg-surface-2 p-2 text-xs" dir="ltr">{{ $cronUrl }}</code>
        </div>
        <a href="{{ url('/login') }}" class="btn btn-primary btn-lg mt-6">{{ __('installer.go_to_login') }}<x-ui.icon name="arrow-right" size="5" class="rtl:rotate-180" /></a>
    </div>
</x-installer::layout>
