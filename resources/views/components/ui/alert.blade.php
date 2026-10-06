@props(['type' => 'success'])
<div role="status" {{ $attributes->class([
    'mb-4 rounded-lg p-3 text-sm',
    'bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200' => $type === 'success',
    'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200' => $type === 'error',
]) }}>{{ $slot }}</div>
