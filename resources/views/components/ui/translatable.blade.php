{{--
    One text field per language with a tab switcher, for menu names and descriptions.
    Values come from old() after a failed validation, otherwise from $values ([locale => text]).
    The first locale is the restaurant's default language and is the required one.
--}}
@props(['name', 'label', 'locales', 'values' => [], 'textarea' => false, 'required' => false, 'rows' => 3, 'maxlength' => 160])
@php
    $values = (array) $values;
    $old = old($name);
    $current = is_array($old) ? $old : $values;
@endphp
<div x-data="{ lang: @js($locales[0]) }">
    <div class="mb-1.5 flex items-center justify-between gap-3">
        <label for="{{ $name }}-{{ $locales[0] }}" class="text-sm font-medium">{{ $label }}@if ($required)<span class="text-red-600" aria-hidden="true"> *</span>@endif</label>
        @if (count($locales) > 1)
            <div class="inline-flex rounded-lg bg-surface-2 p-0.5" role="tablist" aria-label="{{ __('menu.languages') }}">
                @foreach ($locales as $code)
                    <button type="button" role="tab" x-on:click="lang = '{{ $code }}'" :aria-selected="lang === '{{ $code }}'"
                            :class="lang === '{{ $code }}' ? 'bg-surface text-fg shadow-sm' : 'text-muted hover:text-fg'"
                            class="rounded-md px-2.5 py-1 text-xs font-semibold uppercase transition">{{ $code }}@if (empty($current[$code]) && $code !== $locales[0])<span class="ms-1 inline-block size-1.5 rounded-full bg-brand-500 align-middle" title="{{ __('menu.not_translated') }}"></span>@endif</button>
                @endforeach
            </div>
        @endif
    </div>
    @foreach ($locales as $code)
        <div x-show="lang === '{{ $code }}'" @if (! $loop->first) x-cloak @endif>
            @if ($textarea)
                <textarea id="{{ $name }}-{{ $code }}" name="{{ $name }}[{{ $code }}]" rows="{{ $rows }}" maxlength="{{ $maxlength }}" dir="auto" class="field" @if ($loop->first && $required) required @endif>{{ $current[$code] ?? '' }}</textarea>
            @else
                <input id="{{ $name }}-{{ $code }}" name="{{ $name }}[{{ $code }}]" type="text" maxlength="{{ $maxlength }}" dir="auto" value="{{ $current[$code] ?? '' }}" class="field" @if ($loop->first && $required) required @endif>
            @endif
        </div>
    @endforeach
    @error($name.'.'.$locales[0])<p class="mt-1.5 flex items-center gap-1 text-sm text-red-600 dark:text-red-400" role="alert"><x-ui.icon name="alert" size="4" />{{ $message }}</p>@enderror
</div>
