<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $restaurant->publicUrl('about') }}">
    @foreach ($restaurant->menuLocales() as $code)<link rel="alternate" hreflang="{{ $code }}" href="{{ $restaurant->publicUrl('about') }}?lang={{ $code }}">@endforeach
    <meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ $description }}"><meta property="og:type" content="restaurant.restaurant">
    @if ($logo)<meta property="og:image" content="{{ $logo }}">@endif
    @unless ($s['indexable'])<meta name="robots" content="noindex">@endunless
    <meta name="theme-color" content="{{ $restaurant->brandColor() }}">
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    <style>
        :root { --brand: {{ $restaurant->brandColor() }} }
        body { margin: 0; font: 16px/1.6 system-ui, sans-serif; color: #1c1917; background: #faf9f7 }
        header { background: var(--brand); color: #fff; padding: 3rem 1.25rem; text-align: center }
        header img { width: 84px; height: 84px; border-radius: 20px; object-fit: cover; background: #fff }
        h1 { margin: .75rem 0 0; font-size: 2rem; line-height: 1.15 }
        main { max-width: 42rem; margin: 0 auto; padding: 1.5rem 1.25rem 4rem }
        section { background: #fff; border-radius: 1rem; padding: 1.25rem; margin-top: 1rem; box-shadow: 0 1px 2px rgba(0,0,0,.06) }
        h2 { margin: 0 0 .5rem; font-size: 1.05rem } table { width: 100%; border-collapse: collapse } td { padding: .25rem 0 } td:last-child { text-align: end }
        .btn { display: inline-block; margin: .25rem .25rem 0 0; padding: .75rem 1.25rem; border-radius: .75rem; background: var(--brand); color: #fff; font-weight: 600; text-decoration: none }
        .pic figure { margin: 0 } .pic img, .gal img { width: 100%; height: auto; border-radius: .75rem; display: block } figcaption { margin-top: .4rem; font-size: .9rem; opacity: .75 }
        .gal { display: grid; grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr)); gap: .5rem } .gal a { display: block; aspect-ratio: 1; overflow: hidden; border-radius: .75rem } .gal img { height: 100%; object-fit: cover }
        .prose p { margin: .4rem 0 } .prose ul { padding-inline-start: 1.25rem } .prose a { color: var(--brand) }
        .btn.alt { background: #1c1917 } .btn:focus-visible { outline: 3px solid #1c1917; outline-offset: 2px }
        @media (prefers-color-scheme: dark) { body { background: #151413; color: #f3f1ee } section { background: #211f1d } }
    </style>
</head>
<body>
    <header>
        @if ($logo)<img src="{{ $logo }}" alt="">@endif
        <h1>{{ $restaurant->name }}</h1>
        @if ($restaurant->badgeKey())<p><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold menu-accent">★ {{ __('platform.badge_'.$restaurant->badgeKey()) }}</span></p>@endif
        @if ($rating)<p>★ {{ number_format($rating['average'], 1) }} · {{ trans_choice('marketing.review_count', $rating['count'], ['count' => $rating['count']]) }}</p>@endif
    </header>
    <main>
        <p><a class="btn" href="{{ $restaurant->publicUrl() }}">{{ __('customer.about_menu') }}</a>
            @if ($restaurant->phone)<a class="btn alt" href="tel:{{ preg_replace('/[^\d+]/', '', $restaurant->phone) }}">{{ __('customer.about_call') }}</a>@endif
            @if ($s['map_url'])<a class="btn alt" href="{{ $s['map_url'] }}" rel="noopener" target="_blank">{{ __('customer.about_directions') }}</a>@endif</p>

        @php
            $t = fn ($map) => is_array($map) ? ($map[$locale] ?? $map[$restaurant->locale] ?? (reset($map) ?: '')) : '';
            $blocks = $s['blocks'];
            $photos = collect($s['gallery'])->filter(fn ($p) => isset($urls[$p['media']]));
        @endphp

        @if ($blocks !== [])
            @foreach ($blocks as $b)
                @switch($b['type'])
                    @case('text')
                        @if ($t($b['title']) !== '' || $t($b['body']) !== '')<section>@if ($t($b['title']) !== '')<h2>{{ $t($b['title']) }}</h2>@endif @if ($t($b['body']) !== '')<div class="prose">{!! markdown_safe($t($b['body'])) !!}</div>@endif</section>@endif
                        @break
                    @case('image')
                        @if (isset($urls[$b['media']]))<section class="pic"><figure><img src="{{ $urls[$b['media']] }}" alt="{{ $t($b['caption']) }}" loading="lazy">@if ($t($b['caption']) !== '')<figcaption>{{ $t($b['caption']) }}</figcaption>@endif</figure></section>@endif
                        @break
                    @case('button')
                        @php
                            $href = match ($b['target']) { 'menu' => $restaurant->publicUrl(), 'reserve' => $restaurant->publicUrl('reserve'), 'map' => $s['map_url'], 'phone' => $restaurant->phone ? 'tel:'.preg_replace('/[^\d+]/', '', $restaurant->phone) : '', default => $b['url'] };
                        @endphp
                        @if ($href)<p><a class="btn {{ $b['style'] === 'dark' ? 'alt' : '' }}" href="{{ $href }}" @if (str_starts_with($href, 'https://') && $b['target'] !== 'menu' && $b['target'] !== 'reserve') rel="noopener" target="_blank" @endif>{{ $t($b['label']) }}</a></p>@endif
                        @break
                    @case('gallery')
                        @include('storefront::partials.about-gallery')
                        @break
                    @case('hours')
                        @include('storefront::partials.about-hours')
                        @break
                    @case('contact')
                        @include('storefront::partials.about-contact')
                        @break
                    @case('follow')
                        @include('storefront::partials.about-follow')
                        @break
                @endswitch
            @endforeach
        @else
            @if ($about)<section><h2>{{ __('customer.about_us') }}</h2><p style="white-space: pre-line">{{ $about }}</p></section>@endif
            @include('storefront::partials.about-gallery')
            @include('storefront::partials.about-hours')
            @include('storefront::partials.about-contact')
            @include('storefront::partials.about-follow')
        @endif
    </main>
</body>
</html>
