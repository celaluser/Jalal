@props(['name', 'label' => null, 'type' => 'text', 'value' => null])
@php
    // Dotted old() key for array-style names such as limits[products].
    $oldKey = str_replace(['[', ']'], ['.', ''], $name);
@endphp
<div>
    @if ($label)
        <label for="{{ $name }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
    @endif
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password') value="{{ old($oldKey, $value) }}" @endif
           {{ $attributes->class(['block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-950', 'border-red-500' => $errors->has($oldKey)]) }}
           @error($oldKey) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
    @error($oldKey)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
    @enderror
</div>
