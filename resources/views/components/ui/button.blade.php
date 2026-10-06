@props(['variant' => 'primary', 'type' => 'submit'])
<button type="{{ $type }}" {{ $attributes->class([
    'inline-flex w-full items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 disabled:opacity-50',
    'bg-brand-600 text-white hover:bg-brand-700' => $variant === 'primary',
    'bg-gray-100 text-gray-900 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700' => $variant === 'secondary',
    'bg-red-600 text-white hover:bg-red-700' => $variant === 'danger',
]) }}>{{ $slot }}</button>
