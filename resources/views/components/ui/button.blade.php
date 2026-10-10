@props(['variant' => 'primary', 'type' => 'submit', 'size' => null, 'block' => true, 'icon' => null])
<button type="{{ $type }}" {{ $attributes->class([
    'btn',
    'w-full' => $block,
    'btn-primary' => $variant === 'primary',
    'btn-secondary' => $variant === 'secondary',
    'btn-ghost' => $variant === 'ghost',
    'btn-dark' => $variant === 'dark',
    'btn-danger' => $variant === 'danger',
    'btn-sm' => $size === 'sm',
    'btn-lg' => $size === 'lg',
]) }}>@if ($icon)<x-ui.icon :name="$icon" size="4" />@endif{{ $slot }}</button>
