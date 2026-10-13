{{-- Ask for the two site fonts early, so the page is not drawn in a fallback font and then re-flowed when they arrive (a layout shift). --}}
@php
    static $fonts = null;
    $fonts ??= collect(['inter-latin-wght-normal', 'bricolage-grotesque-latin-wght-normal'])->map(function ($name) {
        $file = collect(glob(public_path('build/assets/'.$name.'-*.woff2')) ?: [])->first();

        return $file ? asset('build/assets/'.basename($file)) : null;
    })->filter()->values();
@endphp
@foreach ($fonts as $url)<link rel="preload" href="{{ $url }}" as="font" type="font/woff2" crossorigin>
@endforeach
