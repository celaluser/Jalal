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
        .btn.alt { background: #1c1917 } .btn:focus-visible { outline: 3px solid #1c1917; outline-offset: 2px }
        @media (prefers-color-scheme: dark) { body { background: #151413; color: #f3f1ee } section { background: #211f1d } }
    </style>
</head>
<body>
    <header>
        @if ($logo)<img src="{{ $logo }}" alt="">@endif
        <h1>{{ $restaurant->name }}</h1>
        @if ($rating)<p>★ {{ number_format($rating['average'], 1) }} · {{ trans_choice('marketing.review_count', $rating['count'], ['count' => $rating['count']]) }}</p>@endif
    </header>
    <main>
        <p><a class="btn" href="{{ $restaurant->publicUrl() }}">{{ __('customer.about_menu') }}</a>
            @if ($restaurant->phone)<a class="btn alt" href="tel:{{ preg_replace('/[^\d+]/', '', $restaurant->phone) }}">{{ __('customer.about_call') }}</a>@endif
            @if ($s['map_url'])<a class="btn alt" href="{{ $s['map_url'] }}" rel="noopener" target="_blank">{{ __('customer.about_directions') }}</a>@endif</p>

        @if ($about)<section><h2>{{ __('customer.about_us') }}</h2><p style="white-space: pre-line">{{ $about }}</p></section>@endif

        @if (collect($s['hours'])->filter()->isNotEmpty())
            <section><h2>{{ __('customer.about_hours') }}</h2>
                <table>@foreach (range(0, 6) as $i)@if (! empty($s['hours'][$i]))<tr><td>{{ __('customer.about_day_'.$i) }}</td><td dir="ltr">{{ $s['hours'][$i] }}</td></tr>@endif @endforeach</table>
            </section>
        @endif

        @if ($restaurant->address || $restaurant->city || $restaurant->phone)
            <section><h2>{{ __('customer.about_contact') }}</h2>
                <p>{{ collect([$restaurant->address, $restaurant->city, $restaurant->country])->filter()->implode(', ') }}@if ($restaurant->phone)<br><span dir="ltr">{{ $restaurant->phone }}</span>@endif</p>
            </section>
        @endif

        @if (collect([$marketing['link_instagram'], $marketing['link_facebook'], $marketing['link_website']])->filter()->isNotEmpty())
            <section><h2>{{ __('customer.about_follow') }}</h2>
                @foreach (['link_instagram' => 'Instagram', 'link_facebook' => 'Facebook', 'link_website' => __('customer.about_website')] as $k => $label)@if ($marketing[$k])<a class="btn alt" href="{{ $marketing[$k] }}" rel="noopener" target="_blank">{{ $label }}</a>@endif @endforeach
            </section>
        @endif
    </main>
</body>
</html>
