@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null])
<div>
    @if ($label)<label for="{{ $name }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>@endif
    <select id="{{ $name }}" name="{{ $name }}" {{ $attributes->class('block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-950') }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @error($name)<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
</div>
