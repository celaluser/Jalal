@props(['name', 'label', 'checked' => false, 'value' => '1'])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    // After a failed validation use what the user submitted; otherwise the stored value.
    $isChecked = session()->hasOldInput() ? (bool) old($key) : (bool) $checked;
@endphp
<label class="flex cursor-pointer items-center gap-2.5 text-sm">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($isChecked) class="check">
    <span>{{ $label }}</span>
</label>
