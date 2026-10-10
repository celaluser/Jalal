{{-- Palette chosen by the platform admin (Settings > General). Values are derived hex colours only. --}}
@php($brandCss = \App\Modules\Core\Support\BrandPalette::css(platform_setting('general.brand_color'), platform_setting('general.accent_color')))
@if ($brandCss !== '')<style id="brand-palette">{!! $brandCss !!}</style>@endif
