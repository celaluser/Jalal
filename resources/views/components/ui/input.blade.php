@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null])
@php
    // Dotted old() key for array-style names such as limits[products].
    $oldKey = str_replace(['[', ']'], ['.', ''], $name);
@endphp
<div>
    @if ($label)
        <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>
    @endif
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password') value="{{ old($oldKey, $value) }}" @endif
           {{ $attributes->class(['field']) }}
           @error($oldKey) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
    @if ($hint && ! $errors->has($oldKey))<p class="mt-1.5 text-xs text-muted">{{ $hint }}</p>@endif
    @error($oldKey)
        <p id="{{ $name }}-error" class="mt-1.5 flex items-center gap-1 text-sm text-red-600 dark:text-red-400" role="alert"><x-ui.icon name="alert" size="4" />{{ $message }}</p>
    @enderror
</div>
