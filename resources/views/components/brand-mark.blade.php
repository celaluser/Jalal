@props(['size' => 8, 'dark' => false])
{{-- The product's logo: the uploaded one if there is one, otherwise the built-in QR mark. $dark = shown on a dark background. --}}
@php
    $light = \App\Modules\Core\Support\Brand::logo();
    $onDark = \App\Modules\Core\Support\Brand::logoDark();
    $height = ['8' => 'h-8', '9' => 'h-9', '10' => 'h-10'][(string) $size] ?? 'h-8';
@endphp
@if ($dark && $onDark)
    <img src="{{ $onDark }}" alt="" class="{{ $height }} w-auto max-w-[9rem] shrink-0 object-contain" decoding="async">
@elseif ($light && $dark)
    <span class="grid shrink-0 place-items-center rounded-lg bg-white p-1"><img src="{{ $light }}" alt="" class="{{ $height }} w-auto max-w-[8rem] object-contain" decoding="async"></span>
@elseif ($light)
    <img src="{{ $light }}" alt="" class="{{ $height }} w-auto max-w-[9rem] shrink-0 object-contain" decoding="async">
@else
    <x-ui.qr-mark :size="$size" />
@endif
