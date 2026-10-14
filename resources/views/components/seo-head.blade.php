@props(['title', 'description' => null, 'noindex' => false, 'image' => null, 'type' => 'website'])
{{-- Everything a search engine or a link preview reads from the head of a platform page. Controlled in Settings > SEO and Settings > Google. --}}
@php
    $brand = \App\Modules\Core\Support\Brand::class;
    $indexable = ! $noindex && platform_setting('seo.indexing', '1') === '1';
    $current = url()->current();
    $page = (int) request()->query('page', 1);
    $canonical = $current.($page > 1 ? '?page='.$page : '');
    $img = $image ?: $brand::shareImage();
    $languages = array_keys($activeLanguages ?? []);
    $favicon = $brand::favicon();
    $twitter = platform_setting('seo.twitter_handle');
@endphp
<meta name="robots" content="{{ $indexable ? 'index,follow,max-image-preview:large,max-snippet:-1' : 'noindex,nofollow' }}">
@if ($indexable)
    <link rel="canonical" href="{{ $canonical }}">
    @if (count($languages) > 1 && $page <= 1)
        @foreach ($languages as $code)<link rel="alternate" hreflang="{{ $code }}" href="{{ $current.'?lang='.$code }}">@endforeach
        <link rel="alternate" hreflang="x-default" href="{{ $current }}">
    @endif
    <meta property="og:site_name" content="{{ $brand::name() }}">
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
    @if ($description)<meta property="og:description" content="{{ $description }}">@endif
    @if ($img)<meta property="og:image" content="{{ $img }}">@endif
    <meta name="twitter:card" content="{{ $img ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $title }}">
    @if ($description)<meta name="twitter:description" content="{{ $description }}">@endif
    @if ($img)<meta name="twitter:image" content="{{ $img }}">@endif
    @if ($twitter)<meta name="twitter:site" content="{{ '@'.ltrim($twitter, '@') }}">@endif
@endif
@foreach (['google-site-verification' => 'seo.google_site_verification', 'msvalidate.01' => 'seo.bing_verification', 'yandex-verification' => 'seo.yandex_verification', 'facebook-domain-verification' => 'seo.facebook_domain_verification'] as $name => $key)
    @if ($code = platform_setting($key))<meta name="{{ $name }}" content="{{ $code }}">@endif
@endforeach
<meta name="theme-color" content="{{ $brand::color() }}">
@if ($favicon)
    <link rel="icon" href="{{ $favicon }}">
    <link rel="apple-touch-icon" href="{{ $favicon }}">
@endif
@if ($headCode = platform_setting('seo.head_code')){!! $headCode !!}@endif
