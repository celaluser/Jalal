@if (($photos ?? collect())->isNotEmpty())
    <section><h2>{{ __('sitebuilder.photos') }}</h2>
        <div class="gal">@foreach ($photos as $p)<a href="{{ $urls[$p['media']] }}" target="_blank" rel="noopener"><img src="{{ $urls[$p['media']] }}" alt="{{ $p['caption'] ?: $restaurant->name }}" loading="lazy" width="320" height="320"></a>@endforeach</div>
    </section>
@endif
