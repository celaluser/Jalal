@props(['label', 'value', 'hero' => false])
<div {{ $attributes->class('rounded-2xl bg-white p-4 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800') }}>
    <p class="text-sm text-gray-500">{{ $label }}</p>
    {{-- One hero figure per view; proportional figures for big standalone numbers --}}
    <p @class(['font-semibold', 'text-5xl' => $hero, 'text-2xl' => ! $hero])>{{ $value }}</p>
</div>
