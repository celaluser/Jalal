@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null])
<div>
    @if ($label)<label for="{{ $name }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>@endif
    <select id="{{ $name }}" name="{{ $name }}" {{ $attributes->class('field') }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @error($name)<p class="mt-1.5 flex items-center gap-1 text-sm text-red-600 dark:text-red-400" role="alert"><x-ui.icon name="alert" size="4" />{{ $message }}</p>@enderror
</div>
