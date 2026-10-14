<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta http-equiv="refresh" content="900">
    <title>{{ $restaurant->name }}</title>
    <style>{!! $themeCss !!}</style>
    <style>
        html,body{margin:0;height:100%;overflow:hidden;cursor:none}
        .tv{height:100vh;display:flex;flex-direction:column;padding:3vh 4vw;box-sizing:border-box;background:var(--menu-bg);color:var(--menu-fg);font-family:var(--menu-font,system-ui,sans-serif)}
        .tv-head{display:flex;align-items:center;gap:1.5vw;margin-bottom:2vh}
        .tv-head img{height:7vh;border-radius:1vh}
        .tv-brand{font-size:3.2vh;font-weight:700;opacity:.85}
        .tv-slide{display:none;flex:1;min-height:0;flex-direction:column}
        .tv-slide.on{display:flex;animation:tvin .6s ease}
        @keyframes tvin{from{opacity:0;transform:translateY(2vh)}to{opacity:1;transform:none}}
        @media (prefers-reduced-motion:reduce){.tv-slide.on{animation:none}}
        .tv-title{font-size:7vh;font-weight:800;margin:0 0 2.5vh;color:var(--menu-accent);line-height:1.05}
        .tv-sub{font-size:2.4vh;opacity:.7;margin-inline-start:1.5vw;font-weight:500}
        .tv-grid{flex:1;min-height:0;display:grid;grid-template-columns:repeat(2,1fr);gap:2vh 3vw;align-content:start}
        .tv-item{display:flex;align-items:center;gap:1.6vw;padding:1.6vh 1.4vw;border-radius:var(--menu-radius,1.4vh);background:var(--menu-surface);border:1px solid var(--menu-line)}
        .tv-item img{width:11vh;height:11vh;object-fit:cover;border-radius:1.2vh;flex:none}
        .tv-name{font-size:3.4vh;font-weight:700;line-height:1.15}
        .tv-desc{font-size:2.1vh;opacity:.7;margin-top:.4vh;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .tv-price{margin-inline-start:auto;font-size:4vh;font-weight:800;color:var(--menu-accent);white-space:nowrap;text-align:end}
        .tv-old{display:block;font-size:2.2vh;opacity:.6;text-decoration:line-through;font-weight:500}
        .tv-banner{flex:1;display:grid;place-items:center;text-align:center;border-radius:2vh;background-size:cover;background-position:center;position:relative;overflow:hidden}
        .tv-banner::before{content:"";position:absolute;inset:0;background:rgb(0 0 0 / .45)}
        .tv-banner div{position:relative;color:#fff;padding:4vh 6vw}
        .tv-banner h2{font-size:9vh;margin:0;line-height:1.05}
        .tv-banner p{font-size:3.6vh;margin:2vh 0 0}
        .tv-bar{height:.6vh;background:var(--menu-line);margin-top:2vh;border-radius:1vh;overflow:hidden}
        .tv-bar i{display:block;height:100%;width:0;background:var(--menu-accent)}
        .tv-empty{margin:auto;font-size:4vh;opacity:.7}
        @media (max-aspect-ratio:1/1){.tv-grid{grid-template-columns:1fr}.tv-title{font-size:6vh}}
    </style>
</head>
<body>
<div class="tv" data-seconds="{{ $seconds }}">
    <div class="tv-head">@if ($logo)<img src="{{ $logo }}" alt="">@endif<span class="tv-brand">{{ $restaurant->name }}</span></div>
    @forelse ($slides as $slide)
        <section class="tv-slide {{ $loop->first ? 'on' : '' }}" aria-label="{{ $slide['title'] }}">
            @if ($slide['kind'] === 'banner')
                <div class="tv-banner" @if ($slide['image']) style="background-image:url('{{ $slide['image'] }}')" @endif><div><h2>{{ $slide['title'] }}</h2>@if ($slide['text'])<p>{{ $slide['text'] }}</p>@endif</div></div>
            @else
                <h1 class="tv-title">{{ $slide['title'] }}@if ($slide['kind'] === 'menu' && $slide['pages'] > 1)<span class="tv-sub">{{ $slide['page'] }}/{{ $slide['pages'] }}</span>@endif</h1>
                <div class="tv-grid">
                    @foreach ($slide['products'] as $p)
                        <div class="tv-item">
                            @if ($p['image'])<img src="{{ $p['image'] }}" alt="" loading="lazy">@endif
                            <div><div class="tv-name">{{ $p['name'] }}</div>@if ($p['description'])<div class="tv-desc">{{ $p['description'] }}</div>@endif</div>
                            <div class="tv-price">@if ($p['compare_price'])<span class="tv-old">{{ $restaurant->money($p['compare_price']) }}</span>@endif{{ $p['variants'] ? __('customer.from_price').' '.$restaurant->money($p['price']) : $restaurant->money($p['price']) }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @empty
        <p class="tv-empty">{{ __('tv.empty') }}</p>
    @endforelse
    <div class="tv-bar" aria-hidden="true"><i id="tv-progress"></i></div>
</div>
<script>
(function () {
    var slides = [].slice.call(document.querySelectorAll('.tv-slide')), bar = document.getElementById('tv-progress');
    if (slides.length < 1) { return; }
    var secs = parseInt(document.querySelector('.tv').dataset.seconds, 10) || 10, i = 0, t0 = Date.now();
    function show(n) { slides.forEach(function (s, k) { s.classList.toggle('on', k === n); }); t0 = Date.now(); }
    setInterval(function () { i = (i + 1) % slides.length; show(i); }, secs * 1000);
    if (bar) { setInterval(function () { bar.style.width = Math.min(100, (Date.now() - t0) / (secs * 10)) + '%'; }, 200); }
})();
</script>
</body>
</html>
