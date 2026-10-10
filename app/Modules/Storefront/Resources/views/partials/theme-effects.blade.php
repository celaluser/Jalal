{{-- Motion and surface effects of the chosen theme. Every value is one of a fixed list, so nothing here needs escaping. --}}
@php
    $sb = $settings['scrollbar'];
    $rail = in_array($sb, ['gradient', 'glow'], true);
    $bar = $settings['progress'];
@endphp
@if ($part === 'style')
<style>
    :root{--sb-w:10px}
    {{-- Page background --}}
    @if ($settings['bg_style'] === 'gradient')
        .menu-page{background:linear-gradient(160deg,var(--menu-bg),var(--menu-bg-2)) fixed}
    @elseif ($settings['bg_style'] === 'mesh')
        .menu-page{background:radial-gradient(60rem 40rem at 10% -10%,color-mix(in srgb,var(--menu-accent) 55%,transparent),transparent 60%),radial-gradient(50rem 36rem at 100% 10%,color-mix(in srgb,var(--menu-accent-2) 45%,transparent),transparent 60%),linear-gradient(170deg,var(--menu-bg),var(--menu-bg-2)) fixed;background-size:140% 140%,140% 140%,100% 100%}
    @endif
    @if ($settings['animated_bg'] && $settings['bg_style'] !== 'solid')
        body.menu-page{animation:menu-drift 28s ease-in-out infinite alternate}
        @keyframes menu-drift{from{background-position:0 0,100% 0,0 0}to{background-position:12% 8%,88% 12%,0 0}}
    @endif

    {{-- Cards --}}
    @if ($settings['card'] === 'soft')
        .menu-card{border-color:transparent;box-shadow:0 1px 2px rgb(0 0 0/.05),0 8px 24px -8px rgb(0 0 0/.14)}
    @elseif ($settings['card'] === 'glass')
        .menu-card{background:color-mix(in srgb,var(--menu-surface) 62%,transparent);backdrop-filter:blur(14px) saturate(1.3);-webkit-backdrop-filter:blur(14px) saturate(1.3);border-color:color-mix(in srgb,var(--menu-fg) 14%,transparent);box-shadow:0 10px 30px -12px rgb(0 0 0/.5)}
    @elseif ($settings['card'] === 'outline')
        .menu-card{background:var(--menu-surface);border-color:color-mix(in srgb,var(--menu-accent) 45%,var(--menu-line));transition:box-shadow .25s,border-color .25s}
        .menu-card:hover{border-color:var(--menu-accent);box-shadow:0 0 0 1px var(--menu-accent),0 0 22px -2px color-mix(in srgb,var(--menu-accent) 55%,transparent)}
    @endif


    {{-- Decoration behind the page. The sticky category bar also carries the menu-page class, so these rules name the body. --}}
    @if ($settings['decor'] === 'orbs')
        body.menu-page::before,body.menu-page::after{content:"";position:fixed;z-index:-1;border-radius:50%;filter:blur(80px);opacity:.45;pointer-events:none;will-change:transform}
        body.menu-page::before{width:60vmax;height:60vmax;inset-block-start:-22vmax;inset-inline-start:-20vmax;background:var(--menu-accent);animation:menu-orb-a 22s ease-in-out infinite alternate}
        body.menu-page::after{width:52vmax;height:52vmax;inset-block-end:-24vmax;inset-inline-end:-18vmax;background:var(--menu-accent-2);animation:menu-orb-b 26s ease-in-out infinite alternate}
        @keyframes menu-orb-a{to{transform:translate(12vmax,10vmax) scale(1.12)}}
        @keyframes menu-orb-b{to{transform:translate(-10vmax,-12vmax) scale(1.1)}}
    @elseif ($settings['decor'] === 'grid')
        body.menu-page::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;background-image:linear-gradient(color-mix(in srgb,var(--menu-accent) 30%,transparent) 1px,transparent 1px),linear-gradient(90deg,color-mix(in srgb,var(--menu-accent) 30%,transparent) 1px,transparent 1px);background-size:44px 44px;-webkit-mask-image:radial-gradient(ellipse at 50% 0%,#000 15%,transparent 72%);mask-image:radial-gradient(ellipse at 50% 0%,#000 15%,transparent 72%)}
    @elseif ($settings['decor'] === 'dots')
        body.menu-page::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;background-image:radial-gradient(circle,color-mix(in srgb,var(--menu-accent) 42%,transparent) 1.6px,transparent 1.8px);background-size:22px 22px;-webkit-mask-image:linear-gradient(180deg,#000,transparent 70%);mask-image:linear-gradient(180deg,#000,transparent 70%)}
    @endif

    {{-- Buttons --}}
    @if ($settings['button'] === 'gradient')
        .menu-btn:not(.menu-btn-quiet),.menu-add{background:linear-gradient(135deg,var(--menu-accent),var(--menu-accent-2));box-shadow:0 8px 18px -8px color-mix(in srgb,var(--menu-accent) 80%,transparent)}
    @endif

    {{-- Section headings --}}
    @if ($settings['heading'] === 'gradient')
        .menu-page main h2{background:linear-gradient(90deg,var(--menu-fg) 10%,var(--menu-accent) 60%,var(--menu-accent-2));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent;width:fit-content}
    @elseif ($settings['heading'] === 'glow')
        .menu-page main h2{text-shadow:0 0 14px color-mix(in srgb,var(--menu-accent) 65%,transparent),0 0 2px color-mix(in srgb,var(--menu-accent) 80%,transparent)}
    @endif

    {{-- The welcome header keeps its own text colour on the brand-coloured background --}}
    @if ($settings['heading'] !== 'plain')
        .menu-hero h1,.menu-hero h2,.menu-hero h3{background:none;-webkit-text-fill-color:currentColor;color:inherit;text-shadow:none}
    @endif

    {{-- What a card does when it is touched or hovered --}}
    @if ($settings['hover'] !== 'none')
        .menu-card{transition:transform .25s cubic-bezier(.2,.8,.2,1),box-shadow .25s,border-color .25s}
        .menu-card:active{transform:scale(.985)}
        @media (hover:hover){
            @if ($settings['hover'] === 'lift')
                .menu-card:hover{transform:translateY(-4px);box-shadow:0 20px 36px -18px rgb(0 0 0/.4)}
            @elseif ($settings['hover'] === 'tilt')
                .menu-card:hover{transform:perspective(800px) rotateX(2deg) rotateY(-2.5deg) translateY(-3px);box-shadow:0 20px 34px -18px rgb(0 0 0/.35)}
            @else
                .menu-card:hover{box-shadow:0 0 0 1px var(--menu-accent),0 0 28px -4px color-mix(in srgb,var(--menu-accent) 60%,transparent)}
            @endif
        }
    @endif

    {{-- Scroll bar of the page --}}
    @if ($sb === 'slim')
        html{scrollbar-width:thin;scrollbar-color:var(--menu-muted) transparent}
        html::-webkit-scrollbar{width:6px}html::-webkit-scrollbar-thumb{background:var(--menu-muted);border-radius:6px}
    @elseif ($sb === 'accent')
        html{scrollbar-color:var(--menu-accent) var(--menu-surface)}
        html::-webkit-scrollbar{width:var(--sb-w)}html::-webkit-scrollbar-track{background:var(--menu-surface)}html::-webkit-scrollbar-thumb{background:var(--menu-accent);border-radius:10px}
    @elseif ($sb === 'pill')
        html{scrollbar-color:var(--menu-accent) transparent}
        html::-webkit-scrollbar{width:12px}html::-webkit-scrollbar-track{background:transparent}
        html::-webkit-scrollbar-thumb{background:linear-gradient(var(--menu-accent),var(--menu-accent-2));border-radius:12px;border:3px solid var(--menu-bg)}
    @elseif ($rail)
        html{scrollbar-width:none}html::-webkit-scrollbar{display:none}
    @endif

    {{-- Animated side rail (gradient and glow): decorative, the page still scrolls normally --}}
    @if ($rail)
        .menu-rail{position:fixed;inset-block:0;inset-inline-end:3px;width:5px;z-index:60;pointer-events:none}
        .menu-rail i{position:absolute;inset-inline:0;top:0;height:var(--rail-h,20%);transform:translateY(var(--rail-y,0));border-radius:5px;
            background:linear-gradient(180deg,var(--menu-accent),var(--menu-accent-2),var(--menu-accent));background-size:100% 300%;animation:menu-rail-flow 3.2s linear infinite;will-change:transform}
        @if ($sb === 'glow')
            .menu-rail i{box-shadow:0 0 6px var(--menu-accent),0 0 16px color-mix(in srgb,var(--menu-accent) 70%,transparent)}
        @endif
        @keyframes menu-rail-flow{to{background-position:0 300%}}
    @endif

    {{-- Reading progress bar along the top --}}
    @if ($bar)
        .menu-progress{position:fixed;inset-inline:0;top:0;height:3px;z-index:70;pointer-events:none;transform-origin:left;transform:scaleX(var(--progress,0));
            background:linear-gradient(90deg,var(--menu-accent),var(--menu-accent-2),var(--menu-accent));background-size:200% 100%;animation:menu-bar-flow 2.4s linear infinite}
        [dir=rtl] .menu-progress{transform-origin:right}
        @if ($sb === 'glow')
            .menu-progress{box-shadow:0 0 10px var(--menu-accent)}
        @endif
        @keyframes menu-bar-flow{to{background-position:200% 0}}
    @endif

    {{-- Reveal as cards scroll into view. Only where the browser can tie animation to scrolling; elsewhere cards simply show. --}}
    @if ($settings['reveal'] !== 'none')
        @supports (animation-timeline: view()){
            .menu-card{animation:menu-reveal-{{ $settings['reveal'] }} linear backwards;animation-timeline:view();animation-range:entry 0% entry 55%}
        }
        @keyframes menu-reveal-fade{from{opacity:0}to{opacity:1}}
        @keyframes menu-reveal-rise{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:none}}
        @keyframes menu-reveal-zoom{from{opacity:0;transform:scale(.92)}to{opacity:1;transform:none}}
    @endif
    html[data-menu-calm] .menu-rail,html[data-menu-calm] .menu-progress{display:none}
    html[data-menu-calm] body.menu-page::before,html[data-menu-calm] body.menu-page::after{animation:none!important}
    @media (prefers-reduced-motion:reduce){body.menu-page,body.menu-page::before,body.menu-page::after,.menu-card,.menu-rail i,.menu-progress{animation:none!important}.menu-card{transition:none}}
</style>
@endif
@if ($part === 'body' && ($rail || $bar))
    @if ($rail)<div class="menu-rail" aria-hidden="true"><i></i></div>@endif
    @if ($bar)<div class="menu-progress" aria-hidden="true"></div>@endif
    <script>
    (function () {
        var root = document.documentElement, ticking = false;
        function update() {
            ticking = false;
            var max = Math.max(1, root.scrollHeight - root.clientHeight), p = Math.min(1, Math.max(0, root.scrollTop / max));
            root.style.setProperty('--progress', p.toFixed(4));
            var h = Math.max(8, Math.min(100, root.clientHeight / root.scrollHeight * 100));
            root.style.setProperty('--rail-h', h.toFixed(2) + '%');
            root.style.setProperty('--rail-y', (p * (100 / h * 100 - 100)).toFixed(2) + '%');
        }
        function schedule() { if (!ticking) { ticking = true; requestAnimationFrame(update); } }
        addEventListener('scroll', schedule, { passive: true }); addEventListener('resize', schedule); addEventListener('load', schedule);
        schedule();
    })();
    </script>
@endif
