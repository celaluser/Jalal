@props(['type' => 'success'])
@php($icon = ['success' => 'check-circle', 'error' => 'alert', 'info' => 'info', 'warning' => 'alert'][$type] ?? 'info')
<div role="{{ $type === 'error' ? 'alert' : 'status' }}" {{ $attributes->class([
    'flex items-start gap-3 rounded-xl border px-4 py-3 text-sm',
    'border-accent-200 bg-accent-50 text-accent-900 dark:border-accent-800 dark:bg-accent-900/30 dark:text-accent-100' => $type === 'success',
    'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100' => $type === 'error',
    'border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-100' => $type === 'info',
    'border-brand-200 bg-brand-50 text-brand-900 dark:border-brand-800 dark:bg-brand-900/20 dark:text-brand-100' => $type === 'warning',
]) }}>
    <x-ui.icon :name="$icon" size="5" class="mt-px" />
    <div class="min-w-0 flex-1">{{ $slot }}</div>
</div>
