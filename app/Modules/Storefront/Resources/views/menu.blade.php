@php
    $layout = $settings['layout'];
    $labels = [
        'sold_out' => __('customer.sold_out'),
    ];
    $config = [
        'tree' => $tree,
        'currency' => $currency,
        'quoteUrl' => $base.'/cart/quote',
        'csrf' => csrf_token(),
        'ordering' => $ordering,
        'scroll' => $settings['scroll'],
        'kiosk' => $kiosk,
        'banners' => $banners,
        'account' => $account,
        'orderBase' => $base,
        'notifyLabels' => ['sms' => __('orders.notify_sms'), 'whatsapp' => __('orders.notify_whatsapp'), 'push' => __('orders.notify_push')],
        'currencies' => $currencies,
        'prefsKey' => 'qrmenu.prefs.'.$restaurant->id,
        'pwa' => $kiosk ? null : ['worker' => $base.'/sw.js'],
        'menus' => $menus,
        'storageKey' => 'qrmenu.cart.'.$restaurant->id,
        'diet' => collect($dietary)->mapWithKeys(fn ($d) => [$d => __('menu.diet.'.$d)])->all(),
        'badge' => collect(['new', 'popular', 'chef', 'limited'])->mapWithKeys(fn ($b) => [$b => __('menu.badge_'.$b)])->all(),
        'nutrient' => collect(['protein', 'carbs', 'fat', 'fiber', 'sugar', 'sodium'])->mapWithKeys(fn ($n) => [$n => __('menu.nutrient_'.$n)])->all(),
        'allergen' => collect($allergens)->mapWithKeys(fn ($a) => [$a => __('menu.allergen.'.$a)])->all(),
        't' => collect(['sold_out', 'unavailable', 'not_for_type', 'stock_limit', 'variant_required', 'combo_required', 'option_required', 'option_unavailable', 'invalid_option', 'too_many_options', 'quantity'])->mapWithKeys(fn ($k) => [$k => __('customer.error_'.$k)])->all()
            + collect(['table_required', 'name_required', 'phone_required', 'address_required', 'email_invalid', 'vehicle_required', 'room_required', 'zone_required', 'too_many_items', 'schedule_invalid'])->mapWithKeys(fn ($k) => [$k => __('orders.error_'.$k)])->all()
            + ['reorder_done' => __('orders.reorder_unavailable'), 'from_price' => __('customer.from_price'), 'too_many' => __('customer.too_many'), 'generic_error' => __('customer.generic_error')],
    ];
    $ogImage = $logo;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $restaurant->name }}</title>
    <meta name="description" content="{{ $description }}">
    <meta property="og:title" content="{{ $restaurant->name }}">
    <meta property="og:description" content="{{ $description }}">
    @if ($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endif
    <meta name="theme-color" content="{{ $restaurant->brandColor() }}">
    @unless ($kiosk)
        <link rel="manifest" href="{{ $base }}/manifest.webmanifest">
        <link rel="apple-touch-icon" href="{{ $base }}/pwa-icon-192.png">
        <meta name="mobile-web-app-capable" content="yes">
    @endunless
    @if ($noindex)<meta name="robots" content="noindex">@endif
    <style>{!! $themeCss !!}</style>
    <style>
        html[data-menu-large]{font-size:125%}
        html[data-menu-contrast]{--menu-bg:#fff;--menu-surface:#fff;--menu-fg:#000;--menu-muted:#1f1f1f;--menu-line:#000}
        html[data-menu-contrast] .menu-card{border:2px solid #000}
        html[data-menu-calm] *,html[data-menu-calm] *::before,html[data-menu-calm] *::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}
    </style>
    @if ($kiosk)<style>html{font-size:118%}.kiosk .menu-add{width:3.25rem;height:3.25rem}.kiosk .menu-chip{padding:.6rem 1.1rem}</style>@endif
    @vite(['resources/css/app.css', 'resources/js/storefront.js'])
    @livewireStyles
</head>
<body class="menu-page min-h-screen pb-28 {{ $kiosk ? 'kiosk select-none' : '' }}" x-data="storefront(@js($config))" x-on:keydown.escape.window="closeAll()">
    {{-- Hero: brand colour, restaurant, table and language --}}
    <header class="menu-hero relative overflow-hidden">
        <div class="mx-auto max-w-3xl px-4 {{ $settings['hero'] === 'full' ? 'pb-14' : 'pb-10' }} pt-5">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    @if ($logo)<img src="{{ $logo }}" alt="" class="menu-radius size-14 shrink-0 bg-white object-cover shadow-lg">
                    @else<span class="menu-radius grid size-14 shrink-0 place-items-center bg-white/90 text-xl font-bold text-[#0f1115] shadow-lg">{{ mb_strtoupper(mb_substr($restaurant->name, 0, 1)) }}</span>@endif
                    <div class="min-w-0">
                        <h1 class="display truncate text-2xl font-bold leading-tight">{{ $restaurant->name }}</h1>
                        @if ($table)<p class="mt-0.5 inline-flex items-center gap-1.5 rounded-full bg-black/15 px-2.5 py-0.5 text-sm font-semibold"><x-ui.icon name="qr" size="4" />{{ table_label($table['name']) }}</p>
                        @elseif ($restaurant->city)<p class="truncate text-sm opacity-80">{{ $restaurant->city }}</p>@endif
                        @if ($rating)<p class="mt-1 inline-flex items-center gap-1 text-sm font-semibold" aria-label="{{ trans_choice('marketing.review_count', $rating['count'], ['count' => $rating['count']]) }}, {{ number_format($rating['average'], 1) }}"><svg class="size-4 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><span class="tnum">{{ number_format($rating['average'], 1) }}</span><span class="font-normal opacity-80">({{ $rating['count'] }})</span></p>@endif
                    </div>
                </div>
                @if ($currencies)
                    <label class="sr-only" for="cur">{{ __('customer.currency') }}</label>
                    <select id="cur" x-model="curCode" class="shrink-0 rounded-full bg-black/15 px-2 py-1 text-xs font-bold text-inherit">
                        @foreach ($currencies as $c)<option value="{{ $c['code'] }}" class="text-black">{{ $c['code'] }}</option>@endforeach
                    </select>
                @endif
                @unless ($kiosk)<a href="{{ $base }}/account" class="shrink-0 rounded-full bg-black/15 px-2.5 py-1 text-xs font-bold">{{ $account ? ($account['name'] ?: __('customer.account_link')) : __('customer.account_link') }}</a>@endunless
                <button type="button" x-show="installEvent" x-cloak x-on:click="install()" class="shrink-0 rounded-full bg-black/15 px-2.5 py-1 text-xs font-bold">{{ __('customer.install_app') }}</button>
                @if ($settings['dark_toggle'])
                    <button type="button" x-on:click="toggleAlt()" class="grid size-8 shrink-0 place-items-center rounded-full bg-black/15" :aria-pressed="alt" aria-label="{{ __('customer.toggle_theme') }}"><x-ui.icon name="sun" size="4" /></button>
                @endif
                @if (count($languages) > 1)
                    <nav aria-label="{{ __('customer.language') }}" class="flex shrink-0 gap-1">
                        @foreach ($languages as $code => $name)
                            <a href="?lang={{ $code }}" hreflang="{{ $code }}" lang="{{ $code }}" class="rounded-full px-2.5 py-1 text-xs font-bold uppercase {{ $code === $locale ? 'bg-white text-[#0f1115]' : 'bg-black/15' }}" @if ($code === $locale) aria-current="true" @endif title="{{ $name }}">{{ $code }}</a>
                        @endforeach
                    </nav>
                @endif
            </div>
            @if ($settings['hero'] === 'full')
                <p class="display menu-rise mt-6 max-w-xs text-3xl font-bold leading-[1.1]">{{ __('customer.hero_title') }}</p>
                <p class="mt-2 max-w-xs text-sm opacity-90">{{ $table ? __('customer.hero_table') : __('customer.hero_text') }}</p>
            @endif
            {{-- Dish illustrations / photos peeking in from the corner --}}
            <div class="pointer-events-none absolute -end-6 bottom-2 hidden gap-[-1rem] {{ $settings['hero'] === 'full' ? 'sm:flex' : '' }}" aria-hidden="true">
                @foreach (collect($tree)->flatMap(fn ($c) => $c['products'])->take(3) as $i => $p)
                    <img src="{{ $p['image'] ?: $p['art'] }}" alt="" class="size-24 rounded-full border-4 border-white/70 object-cover shadow-xl {{ $i ? '-ms-6' : '' }}">
                @endforeach
            </div>
        </div>
    </header>

    <div class="mx-auto -mt-7 max-w-3xl px-4">
        @if ($branch)
            <p class="menu-card mb-3 flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm" role="status"><span class="font-medium">{{ $branch['name'] }}@unless ($branch['open']) <span class="menu-muted">· {{ __('branches.closed_now') }}</span>@endunless</span>
                @if ($branch['switch'])<a class="underline" href="{{ $base }}?branch=">{{ __('branches.choose_title') }}</a>@endif</p>
        @endif
        @if (session('table_invalid'))<p class="menu-card menu-muted mb-3 px-3 py-2 text-sm" role="status">{{ __('customer.table_invalid') }}</p>@endif

        {{-- Search and filters --}}
        <div class="flex gap-2">
            <div class="menu-card relative flex-1 shadow-lg">
                <x-ui.icon name="search" size="4" class="menu-muted pointer-events-none absolute start-3.5 top-1/2 -translate-y-1/2" />
                <input type="search" x-model="q" placeholder="{{ __('customer.search_placeholder') }}" aria-label="{{ __('customer.search') }}" class="w-full bg-transparent py-3 ps-10 pe-3 text-sm placeholder:opacity-60 focus:outline-none">
            </div>
            <button type="button" class="menu-card relative grid size-[2.9rem] shrink-0 place-items-center shadow-lg" x-on:click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen" aria-label="{{ __('customer.filters') }}">
                <x-ui.icon name="sliders" size="5" />
                <span x-show="filterCount" x-cloak class="menu-accent absolute -end-1 -top-1 grid size-4 place-items-center rounded-full text-[10px] font-bold" x-text="filterCount"></span>
            </button>
        </div>
        <div x-show="filtersOpen" x-cloak class="menu-card mt-2 space-y-3 p-3">
            <div>
                <p class="menu-muted mb-1.5 text-xs font-semibold uppercase tracking-wide">{{ __('customer.diet') }}</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($dietary as $d)<button type="button" class="menu-chip" :aria-pressed="diet.includes('{{ $d }}')" x-on:click="toggleIn('diet', '{{ $d }}')">{{ __('menu.diet.'.$d) }}</button>@endforeach
                </div>
            </div>
            <div>
                <p class="menu-muted mb-1.5 text-xs font-semibold uppercase tracking-wide">{{ __('customer.avoid_allergens') }}</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($allergens as $a)<button type="button" class="menu-chip" :aria-pressed="avoid.includes('{{ $a }}')" x-on:click="toggleIn('avoid', '{{ $a }}')">{{ __('menu.allergen.'.$a) }}</button>@endforeach
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <p class="menu-muted mb-1.5 text-xs font-semibold uppercase tracking-wide">{{ __('customer.filter_spice') }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" class="menu-chip" :aria-pressed="spice === 0" x-on:click="spice = 0">{{ __('customer.filter_spice_any') }}</button>
                        @foreach ([1, 2, 3] as $level)<button type="button" class="menu-chip" :aria-pressed="spice === {{ $level }}" x-on:click="spice = {{ $level }}">{{ str_repeat('🌶', $level) }}<span class="sr-only"> {{ __('menu.spice_'.$level) }}</span></button>@endforeach
                    </div>
                </div>
                <div x-show="priceCeil > 0">
                    <label for="max-price" class="menu-muted mb-1.5 flex items-center justify-between text-xs font-semibold uppercase tracking-wide"><span>{{ __('customer.filter_price') }}</span><span class="tnum normal-case" x-text="money(maxPrice * 100)"></span></label>
                    <input id="max-price" type="range" min="0" :max="priceCeil" step="1" x-model.number="maxPrice" class="w-full accent-[var(--menu-accent)]">
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="menu-chip" :aria-pressed="favOnly" x-on:click="favOnly = !favOnly">♥ {{ __('customer.filter_favorites') }}</button>
                <label class="flex items-center gap-2 text-sm"><span class="menu-muted">{{ __('customer.sort') }}</span>
                    <select x-model="sort" class="rounded-lg border bg-transparent px-2 py-1 text-sm menu-line">
                        <option value="menu">{{ __('customer.sort_default') }}</option><option value="price_asc">{{ __('customer.sort_price_asc') }}</option><option value="price_desc">{{ __('customer.sort_price_desc') }}</option>
                    </select></label>
            </div>
            <div>
                <p class="menu-muted mb-1.5 text-xs font-semibold uppercase tracking-wide">{{ __('customer.a11y') }}</p>
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" class="menu-chip" :aria-pressed="a11y.large" x-on:click="a11y.large = !a11y.large">{{ __('customer.a11y_large') }}</button>
                    <button type="button" class="menu-chip" :aria-pressed="a11y.contrast" x-on:click="a11y.contrast = !a11y.contrast">{{ __('customer.a11y_contrast') }}</button>
                    <button type="button" class="menu-chip" :aria-pressed="a11y.calm" x-on:click="a11y.calm = !a11y.calm">{{ __('customer.a11y_calm') }}</button>
                </div>
            </div>
            <button type="button" class="text-sm font-medium underline underline-offset-2" x-show="hasFilters" x-on:click="resetFilters()">{{ __('customer.reset_filters') }}</button>
        </div>
    </div>

    {{-- At the table: ask for service and see the shared bill --}}
    <section class="mx-auto mt-4 max-w-3xl px-4" x-show="ord.requestUrl" x-cloak aria-label="{{ __('orders.requests') }}">
        <div class="menu-scroll flex gap-2 overflow-x-auto pb-1">
            <button type="button" class="menu-chip !px-3.5 !py-1.5 !text-sm !font-semibold" x-on:click="askService('waiter')">🙋 {{ __('orders.call_waiter') }}</button>
            <button type="button" class="menu-chip !px-3.5 !py-1.5 !text-sm !font-semibold" x-on:click="askService('bill')">🧾 {{ __('orders.ask_bill') }}</button>
            <button type="button" class="menu-chip !px-3.5 !py-1.5 !text-sm !font-semibold" x-on:click="askService('water')">💧 {{ __('orders.ask_water') }}</button>
            <button type="button" class="menu-chip !px-3.5 !py-1.5 !text-sm !font-semibold" x-on:click="openTab()">🍽 {{ __('orders.tab_title') }}</button>
        </div>
    </section>

    {{-- Shared table bill --}}
    <div x-show="tab" x-cloak x-transition.opacity class="fixed inset-0 z-50 grid place-items-end bg-black/50 sm:place-items-center" x-on:click.self="tab = null" role="dialog" aria-modal="true" aria-label="{{ __('orders.tab_title') }}">
        <div class="menu-page w-full max-w-lg overflow-hidden rounded-t-3xl p-5 shadow-2xl sm:rounded-3xl">
            <div class="flex items-start justify-between gap-3"><div><h2 class="display text-xl font-bold">{{ __('orders.tab_title') }} · <span x-text="tab ? tab.table : ''"></span></h2><p class="menu-muted text-sm">{{ __('orders.tab_text') }}</p></div>
                <button type="button" class="menu-card grid size-9 shrink-0 place-items-center" x-on:click="tab = null" aria-label="{{ __('customer.close') }}"><x-ui.icon name="x" size="5" /></button></div>
            <p class="menu-muted mt-5 text-center" x-show="tab && !tab.orders.length">{{ __('orders.tab_empty') }}</p>
            <ul class="mt-4 max-h-[50vh] space-y-3 overflow-y-auto">
                <template x-for="o in (tab ? tab.orders : [])" :key="o.number">
                    <li class="menu-card p-3"><p class="flex justify-between font-semibold"><span x-text="'#' + o.number"></span><span class="tnum" x-text="o.total"></span></p>
                        <p class="menu-muted text-sm" x-text="o.items.join(', ')"></p></li>
                </template>
            </ul>
            <p class="menu-line mt-4 flex justify-between border-t pt-3 text-lg font-bold" x-show="tab && tab.orders.length"><span>{{ __('orders.tab_total') }}</span><span class="tnum" x-text="tab ? tab.total : ''"></span></p>
        </div>
    </div>

    {{-- Banners --}}
    <section class="mx-auto mt-5 max-w-3xl" x-show="banners.length && !hasFilters" x-cloak aria-label="{{ __('marketing.banners_title') }}">
        <div class="menu-scroll menu-snap flex gap-3 overflow-x-auto px-4 pb-1">
            <template x-for="b in banners" :key="b.id">
                <component :is="'div'">
                    <article class="menu-card relative flex w-72 shrink-0 flex-col overflow-hidden sm:w-80">
                        <img x-show="b.image" :src="b.image" alt="" loading="lazy" class="aspect-[16/7] w-full object-cover">
                        <div class="p-3.5">
                            <p class="display font-bold leading-snug" x-text="b.title"></p>
                            <p class="menu-muted mt-1 text-sm leading-snug" x-show="b.text" x-text="b.text"></p>
                            <a x-show="b.link" :href="b.link" :target="b.link && b.link.startsWith('http') ? '_blank' : null" rel="noopener" class="menu-btn mt-3 !px-3 !py-1.5 !text-sm" x-text="b.button || @js(__('customer.choose'))"></a>
                        </div>
                    </article>
                </component>
            </template>
        </div>
    </section>

    {{-- Most loved --}}
    <section class="mx-auto mt-6 max-w-3xl" x-show="featured.length > 0 && !hasFilters" x-cloak aria-label="{{ __('customer.most_loved') }}">
        <h2 class="display mb-3 flex items-center gap-2 px-4 text-xl font-bold"><x-ui.icon name="sparkles" size="5" class="menu-accent-text" />{{ __('customer.most_loved') }}</h2>
        <div class="menu-scroll menu-snap flex gap-3 overflow-x-auto px-4 pb-2">
            <template x-for="p in featured" :key="'f' + p.id">
                <article class="menu-card relative w-44 shrink-0 overflow-hidden sm:w-52">
                    <button type="button" class="menu-img block aspect-[4/3] w-full overflow-hidden" x-on:click="open(p)" tabindex="-1" aria-hidden="true"><img :src="pic(p)" alt="" loading="lazy" class="size-full object-cover"></button>
                    <div class="p-3">
                        <button type="button" class="block w-full truncate text-start font-semibold" x-on:click="open(p)" x-text="p.name"></button>
                        <p class="mt-1 flex items-center justify-between"><span class="tnum font-bold" x-text="priceText(p)"></span>
                            <button type="button" class="menu-add !size-9" x-on:click="quickAdd(p, $event)" :aria-label="(p.option_groups.length ? @js(__('customer.choose')) : @js(__('customer.add'))) + ' ' + p.name"><x-ui.icon name="plus" size="5" /></button></p>
                    </div>
                </article>
            </template>
        </div>
    </section>

    {{-- Menu switcher: only when more than one menu is open right now --}}
    @if ($menus)
        <nav class="mx-auto mt-4 flex max-w-3xl gap-2 overflow-x-auto px-4" aria-label="{{ __('menu.menus_title') }}" x-show="!hasFilters">
            @foreach ($menus as $m)
                <button type="button" class="menu-chip !px-4 !py-1.5 !text-sm !font-semibold" :aria-pressed="menuId === {{ $m['id'] }}" x-on:click="menuId = {{ $m['id'] }}; window.scrollTo({ top: 0, behavior: 'smooth' })">{{ $m['name'] }}</button>
            @endforeach
        </nav>
    @endif

    {{-- Category tabs --}}
    @if ($tree)
        <nav class="menu-page menu-line sticky top-0 z-20 mt-3 border-b" aria-label="{{ __('menu.categories') }}">
            <div class="menu-scroll mx-auto flex max-w-3xl gap-2 overflow-x-auto px-4 py-2.5" x-show="visible.length > 0">
                <template x-for="c in visible" :key="c.id">
                    <button type="button" class="menu-chip" x-bind:data-tab="c.id" :aria-current="active === c.id" x-on:click="goTo(c.id)"><span x-show="c.icon" x-text="c.icon + ' '"></span><span x-text="c.name"></span></button>
                </template>
            </div>
        </nav>
    @endif

    {{-- Menu --}}
    <main class="mx-auto max-w-3xl px-4 pt-5">
        @if (! $tree)
            <div class="menu-card px-6 py-14 text-center"><p class="text-lg font-semibold">{{ __('customer.empty_title') }}</p><p class="menu-muted mt-1">{{ __('customer.empty_text') }}</p></div>
        @else
            <template x-for="c in visible" :key="c.id">
                <section class="mb-8 scroll-mt-16" :id="'cat-' + c.id" :data-cat="c.id" :aria-label="c.name">
                    <h2 class="display mb-1 text-xl font-bold"><span x-show="c.icon" x-text="c.icon + ' '"></span><span x-text="c.name"></span></h2>
                    <p class="menu-muted mb-3 text-sm" x-show="c.description" x-text="c.description"></p>
                    <div class="{{ $layout === 'grid' ? 'grid grid-cols-2 gap-3' : 'space-y-3' }}">
                        <template x-for="p in c.products" :key="p.id">
                            <article class="menu-card relative overflow-hidden {{ $layout === 'list' ? 'flex gap-3 p-3' : 'flex flex-col' }}" :class="p.available ? '' : 'opacity-60'">
                                <button type="button" class="menu-surface absolute end-2 top-2 z-10 grid size-8 place-items-center rounded-full text-base shadow" x-on:click.stop="toggleFav(p)" :aria-pressed="isFav(p)" :aria-label="(isFav(p) ? @js(__('customer.favorite_remove')) : @js(__('customer.favorite_add'))) + ' ' + p.name"><span :class="isFav(p) ? 'text-rose-500' : 'opacity-50'" x-text="isFav(p) ? '♥' : '♡'"></span></button>
                                @if ($settings['show_images'] && $layout !== 'list')
                                    <div class="menu-img relative w-full overflow-hidden {{ $layout === 'grid' ? 'aspect-[4/3]' : 'aspect-[16/9]' }}">
                                        <button type="button" x-on:click="open(p)" tabindex="-1" aria-hidden="true" class="block size-full"><img :src="pic(p)" alt="" loading="lazy" class="size-full object-cover"></button>
                                        <span class="menu-accent absolute start-2.5 top-2.5 rounded-full px-2.5 py-0.5 text-xs font-bold shadow" x-show="p.featured && p.available">{{ __('customer.featured') }}</span>
                                        <span class="tnum menu-surface absolute bottom-2.5 start-2.5 rounded-full px-2.5 py-1 text-sm font-bold shadow" x-text="priceText(p)"></span>
                                        <button type="button" class="menu-add absolute bottom-2.5 end-2.5" :disabled="!p.available" x-on:click="quickAdd(p, $event)" :aria-label="(p.option_groups.length ? @js(__('customer.choose')) : @js(__('customer.add'))) + ' ' + p.name"><x-ui.icon name="plus" size="5" /></button>
                                    </div>
                                @endif
                                <div class="{{ $layout === 'list' ? 'flex min-w-0 flex-1 flex-col' : 'flex flex-1 flex-col p-3.5' }}">
                                    <button type="button" x-on:click="open(p)" class="block w-full text-start">
                                        <span class="block text-[1.05rem] font-bold leading-snug" x-text="p.name"></span>
                                        <span class="menu-muted mt-1 line-clamp-2 block text-sm leading-snug" x-show="p.description" x-text="p.description"></span>
                                    </button>
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs" x-show="!p.available || p.dietary.length || p.badges.length || p.spice > 0 || (p.featured && {{ $layout === 'list' || ! $settings['show_images'] ? 'true' : 'false' }})">
                                        <template x-for="b in p.badges" :key="b"><span class="menu-accent rounded-full px-2 py-0.5 font-semibold" x-text="cfg_badge[b]"></span></template>
                                        <span class="menu-accent rounded-full px-2 py-0.5 font-semibold" x-show="p.featured && p.available && {{ $layout === 'list' || ! $settings['show_images'] ? 'true' : 'false' }}">{{ __('customer.featured') }}</span>
                                        <span class="rounded-full border px-2 py-0.5 font-semibold menu-line" x-show="!p.available">{{ __('customer.sold_out') }}</span>
                                        <template x-for="d in p.dietary" :key="d"><span class="menu-muted rounded-full border px-2 py-0.5 menu-line" x-text="cfg_diet[d]"></span></template>
                                        <span class="rounded-full border px-2 py-0.5 menu-line" x-show="p.spice > 0" :aria-label="@js(__('customer.spice_label'))" x-text="'🌶'.repeat(p.spice)"></span>
                                    </div>
                                    @if ($layout === 'list' || ! $settings['show_images'])
                                        <div class="mt-auto flex items-center justify-between gap-2 pt-3">
                                            <p class="tnum"><span class="text-lg font-bold" x-text="priceText(p)"></span> <s class="menu-muted ms-1 text-sm" x-show="p.compare_price" x-text="p.compare_price ? money(cents(p.compare_price)) : ''"></s></p>
                                            @if (! $settings['show_images'])
                                                <button type="button" class="menu-btn !px-3 !py-1.5 !text-sm" :disabled="!p.available" x-on:click="quickAdd(p, $event)"><x-ui.icon name="plus" size="4" /><span x-text="p.option_groups.length ? @js(__('customer.choose')) : @js(__('customer.add'))"></span></button>
                                            @endif
                                        </div>
                                    @else
                                        <p class="menu-muted tnum mt-2 text-sm" x-show="p.compare_price"><s x-text="p.compare_price ? money(cents(p.compare_price)) : ''"></s></p>
                                    @endif
                                </div>
                                @if ($settings['show_images'] && $layout === 'list')
                                    <div class="menu-img relative order-last size-28 shrink-0 self-start overflow-hidden menu-radius">
                                        <button type="button" x-on:click="open(p)" tabindex="-1" aria-hidden="true" class="block size-full"><img :src="pic(p)" alt="" loading="lazy" class="size-full object-cover"></button>
                                        <button type="button" class="menu-add absolute bottom-1.5 end-1.5 !size-9" :disabled="!p.available" x-on:click="quickAdd(p, $event)" :aria-label="(p.option_groups.length ? @js(__('customer.choose')) : @js(__('customer.add'))) + ' ' + p.name"><x-ui.icon name="plus" size="5" /></button>
                                    </div>
                                @endif
                            </article>
                        </template>
                    </div>
                </section>
            </template>
            <div x-ref="sentinel" class="h-px" aria-hidden="true"></div>

            <div x-show="visible.length === 0" x-cloak class="menu-card px-6 py-14 text-center">
                <p class="text-lg font-semibold">{{ __('customer.no_results_title') }}</p>
                <p class="menu-muted mt-1">{{ __('customer.no_results_text') }}</p>
                <button type="button" class="menu-btn menu-btn-quiet mt-4" x-on:click="resetFilters()">{{ __('customer.reset_filters') }}</button>
            </div>
        @endif

        @if ($settings['show_credit'])
            <p class="menu-muted pb-4 pt-2 text-center text-xs">{{ __('customer.powered_by', ['name' => config('app.name')]) }}</p>
        @endif
    </main>

    {{-- Without JavaScript the menu is still readable --}}
    <noscript>
        <div class="mx-auto max-w-3xl px-4 pb-10">
            @foreach ($tree as $c)
                <h2 class="mt-6 text-lg font-semibold">{{ $c['name'] }}</h2>
                <ul>@foreach ($c['products'] as $p)<li class="py-1.5">{{ $p['name'] }} — {{ $restaurant->money($p['price']) }}@if ($p['description'])<br><span class="menu-muted text-sm">{{ $p['description'] }}</span>@endif</li>@endforeach</ul>
            @endforeach
        </div>
    </noscript>

    {{-- Kiosk: order placed. The screen resets by itself for the next guest. --}}
    @if ($kiosk)
        <div x-show="kioskDone" x-cloak class="menu-page fixed inset-0 z-50 grid place-items-center p-8 text-center" role="alertdialog" aria-live="assertive">
            <div class="max-w-md">
                <p class="display text-4xl font-bold">{{ __('customer.kiosk_thanks') }}</p>
                <p class="menu-muted mt-3 text-lg">{{ __('customer.kiosk_number') }}</p>
                <p class="display tnum menu-accent-text mt-1 text-7xl font-extrabold" x-text="'#' + (kioskDone ? kioskDone.number : '')"></p>
                <button type="button" class="menu-btn mt-8 !px-8 !py-3 !text-lg" x-on:click="kioskReset()">{{ __('customer.kiosk_new') }}</button>
            </div>
        </div>
    @endif

    {{-- Pop-up banner (once per visit) --}}
    <div x-show="popup" x-cloak x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-black/55 p-5" x-on:click.self="popup = null" role="dialog" aria-modal="true" :aria-label="popup ? popup.title : ''">
        <article class="menu-page menu-card relative w-full max-w-sm overflow-hidden shadow-2xl">
            <button type="button" class="menu-surface absolute end-2 top-2 z-10 grid size-9 place-items-center rounded-full shadow" x-on:click="popup = null" aria-label="{{ __('customer.close') }}"><x-ui.icon name="x" size="5" /></button>
            <img x-show="popup && popup.image" :src="popup ? popup.image : ''" alt="" class="aspect-[16/9] w-full object-cover">
            <div class="p-5">
                <p class="display text-xl font-bold" x-text="popup ? popup.title : ''"></p>
                <p class="menu-muted mt-1.5" x-show="popup && popup.text" x-text="popup ? popup.text : ''"></p>
                <a x-show="popup && popup.link" :href="popup ? popup.link : '#'" x-on:click="popup = null" class="menu-btn mt-4" x-text="popup ? (popup.button || @js(__('customer.choose'))) : ''"></a>
            </div>
        </article>
    </div>

    {{-- Confirmation toast --}}
    <div x-show="toast" x-cloak x-transition.opacity class="pointer-events-none fixed inset-x-0 bottom-24 z-40 flex justify-center px-4" role="status">
        <span class="menu-btn shadow-lg"><x-ui.icon name="check" size="4" /><span x-text="toast"></span></span>
    </div>

    {{-- Cart bar --}}
    <div x-show="count > 0 && !cartOpen && !sheet" x-cloak class="fixed inset-x-0 bottom-0 z-30 p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
        <button type="button" data-cart-bar class="menu-btn mx-auto flex w-full max-w-3xl justify-between !rounded-full !py-4 !text-base shadow-2xl" :class="bumped ? 'menu-pop' : ''" x-on:click="cartOpen = true">
            <span class="flex items-center gap-2"><span class="grid min-w-7 place-items-center rounded-full bg-black/20 px-2 py-0.5 text-sm font-bold" x-text="count"></span>{{ __('customer.view_cart') }}</span>
            <span class="tnum" x-text="subtotal"></span>
        </button>
    </div>

    {{-- Product sheet --}}
    <div x-show="sheet" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" :aria-label="sheet?.product.name" x-on:keydown.tab="trap($event)">
        <div class="absolute inset-0 bg-black/50" x-on:click="sheet = null" x-transition.opacity></div>
        <template x-if="sheet">
            <div class="menu-page relative flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-[1.5rem] shadow-2xl sm:rounded-[1.5rem]" style="border-radius: min(var(--menu-radius) * 1.6, 1.75rem) min(var(--menu-radius) * 1.6, 1.75rem) 0 0">
                <button type="button" x-ref="sheetClose" x-on:click="sheet = null" class="menu-card absolute end-3 top-3 z-10 grid size-9 place-items-center" aria-label="{{ __('customer.close') }}"><x-ui.icon name="x" size="5" /></button>
                <div class="overflow-y-auto">
                    <div class="menu-img relative aspect-[16/10] w-full"><img :src="photos(sheet.product)[sheet.img] || big(sheet.product)" alt="" class="size-full object-cover">
                        <div class="absolute inset-x-0 bottom-2 flex justify-center gap-1.5" x-show="photos(sheet.product).length > 1">
                            <template x-for="(ph, i) in photos(sheet.product)" :key="i"><button type="button" class="size-2.5 rounded-full border border-white/70 transition" :class="sheet.img === i ? 'bg-white' : 'bg-white/30'" x-on:click="sheet.img = i" :aria-label="(i + 1) + ' / ' + photos(sheet.product).length"></button></template>
                        </div>
                    </div>
                    <div class="p-5">
                        <h2 class="display pe-10 text-2xl font-bold leading-tight" x-text="sheet.product.name"></h2>
                        <p class="tnum mt-1 text-lg font-bold"><span x-text="sheetBase ? money(sheetBase) : priceText(sheet.product)"></span> <s class="menu-muted ms-1 text-sm font-normal" x-show="sheet.product.compare_price" x-text="sheet.product.compare_price ? money(cents(sheet.product.compare_price)) : ''"></s></p>
                        <p class="menu-muted mt-1.5 whitespace-pre-line" x-show="sheet.product.description" x-text="sheet.product.description"></p>
                        <p class="menu-muted mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm" x-show="sheet.product.calories || sheet.product.prep_minutes">
                            <span x-show="sheet.product.calories" x-text="@js(__('customer.calories', ['count' => '__'])).replace('__', sheet.product.calories)"></span>
                            <span x-show="sheet.product.prep_minutes" x-text="@js(__('customer.minutes', ['count' => '__'])).replace('__', sheet.product.prep_minutes)"></span>
                        </p>
                        <div class="mt-3 flex flex-wrap gap-1.5" x-show="sheet.product.badges.length">
                            <template x-for="b in sheet.product.badges" :key="b"><span class="menu-accent rounded-full px-2.5 py-0.5 text-xs font-semibold" x-text="cfg_badge[b]"></span></template>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-1.5" x-show="sheet.product.dietary.length">
                            <template x-for="d in sheet.product.dietary" :key="d"><span class="menu-chip !py-0.5" x-text="cfg_diet[d]"></span></template>
                        </div>
                        <template x-if="sheet.product.video">
                            <div class="mt-4">
                                <button type="button" class="menu-btn menu-btn-quiet w-full" x-show="!sheet.video" x-on:click="sheet.video = true"><x-ui.icon name="play" size="4" />{{ __('customer.watch_video') }}</button>
                                <template x-if="sheet.video && sheet.product.video.type === 'embed'"><iframe :src="sheet.product.video.src" class="aspect-video w-full rounded-xl" loading="lazy" allow="encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" title="{{ __('customer.watch_video') }}"></iframe></template>
                                <template x-if="sheet.video && sheet.product.video.type === 'file'"><video :src="sheet.product.video.src" class="aspect-video w-full rounded-xl bg-black" controls playsinline preload="metadata"></video></template>
                            </div>
                        </template>
                        <div class="mt-4" x-show="sheet.product.nutrition || sheet.product.portion_size">
                            <p class="text-sm font-semibold">{{ __('customer.nutrition') }}<span class="menu-muted font-normal" x-show="sheet.product.portion_size" x-text="' · ' + sheet.product.portion_size"></span></p>
                            <dl class="mt-1.5 grid grid-cols-3 gap-2 text-center" x-show="sheet.product.nutrition">
                                <template x-for="(val, key) in (sheet.product.nutrition || {})" :key="key"><div class="menu-card px-2 py-1.5"><dd class="tnum text-sm font-bold" x-text="val + (key === 'sodium' ? ' mg' : ' g')"></dd><dt class="menu-muted text-[11px]" x-text="cfg_nutrient[key]"></dt></div></template>
                            </dl>
                        </div>
                        <p class="mt-3 text-sm" x-show="sheet.product.allergens.length"><span class="font-semibold">{{ __('customer.contains') }}:</span> <span class="menu-muted" x-text="sheet.product.allergens.map((a) => cfg_allergen[a]).join(', ')"></span></p>

                        <fieldset class="mt-5" x-show="(sheet.product.variants || []).length">
                            <legend class="flex w-full items-baseline justify-between gap-2"><span class="font-semibold">{{ __('customer.choose_size') }}</span>
                                <span class="text-xs" :class="sheet.showErrors && variantMissing ? 'font-semibold text-red-600' : 'menu-muted'">{{ __('customer.required') }} · {{ __('customer.choose_one') }}</span></legend>
                            <div class="mt-2 space-y-1.5">
                                <template x-for="v in (sheet.product.variants || [])" :key="v.id">
                                    <label class="menu-card flex cursor-pointer items-center gap-3 px-3 py-2.5" :class="v.available ? '' : 'opacity-50'" :style="sheet.variant === v.id ? 'border-color: var(--menu-accent); box-shadow: 0 0 0 1px var(--menu-accent)' : ''">
                                        <input type="radio" class="size-4" style="accent-color: var(--menu-accent)" name="size" :checked="sheet.variant === v.id" :disabled="!v.available" x-on:click="sheet.variant = v.id">
                                        <span class="flex-1" x-text="v.name + (v.available ? '' : ' · ' + @js(__('customer.sold_out')))"></span>
                                        <span class="tnum text-sm font-semibold" x-text="money(cents(v.price))"></span>
                                    </label>
                                </template>
                            </div>
                        </fieldset>

                        <template x-for="slot in (sheet.product.combo || [])" :key="'s' + slot.id">
                            <fieldset class="mt-5">
                                <legend class="flex w-full items-baseline justify-between gap-2"><span class="font-semibold" x-text="slot.name"></span>
                                    <span class="text-xs" :class="sheet.showErrors && !slot.items.find((i) => i.id === sheet.combo[slot.id] && i.available) ? 'font-semibold text-red-600' : 'menu-muted'">{{ __('customer.required') }} · {{ __('customer.choose_one') }}</span></legend>
                                <div class="mt-2 space-y-1.5">
                                    <template x-for="it in slot.items" :key="it.id">
                                        <label class="menu-card flex cursor-pointer items-center gap-3 px-3 py-2.5" :class="it.available ? '' : 'opacity-50'" :style="sheet.combo[slot.id] === it.id ? 'border-color: var(--menu-accent); box-shadow: 0 0 0 1px var(--menu-accent)' : ''">
                                            <input type="radio" class="size-4" style="accent-color: var(--menu-accent)" :name="'slot' + slot.id" :checked="sheet.combo[slot.id] === it.id" :disabled="!it.available" x-on:click="sheet.combo[slot.id] = it.id">
                                            <span class="flex-1" x-text="it.name + (it.available ? '' : ' · ' + @js(__('customer.sold_out')))"></span>
                                            <span class="menu-muted tnum text-sm" x-text="it.delta > 0 ? '+' + money(Math.round(it.delta * 100)) : @js(__('customer.combo_included'))"></span>
                                        </label>
                                    </template>
                                </div>
                            </fieldset>
                        </template>

                        <template x-for="g in sheet.product.option_groups" :key="g.id">
                            <fieldset class="mt-5">
                                <legend class="flex w-full items-baseline justify-between gap-2">
                                    <span class="font-semibold" x-text="g.name"></span>
                                    <span class="text-xs" :class="sheet.showErrors && groupMissing(g) ? 'font-semibold text-red-600' : 'menu-muted'"
                                          x-text="(g.required ? @js(__('customer.required')) : @js(__('customer.optional'))) + (g.type === 'single' ? ' · ' + @js(__('customer.choose_one')) : (g.max_select ? ' · ' + @js(__('customer.choose_up_to', ['count' => '__'])).replace('__', g.max_select) : ''))"></span>
                                </legend>
                                <div class="mt-2 space-y-1.5">
                                    <template x-for="o in g.options" :key="o.id">
                                        <label class="menu-card flex cursor-pointer items-center gap-3 px-3 py-2.5" :style="isPicked(g, o) ? 'border-color: var(--menu-accent); box-shadow: 0 0 0 1px var(--menu-accent)' : ''">
                                            <input :type="g.type === 'single' ? 'radio' : 'checkbox'" class="size-4" style="accent-color: var(--menu-accent)" :checked="isPicked(g, o)" x-on:click.prevent="pick(g, o)" :name="'g' + g.id">
                                            <span class="flex-1" x-text="o.name"></span>
                                            <span class="menu-muted tnum text-sm" x-show="o.price_delta !== 0" x-text="(o.price_delta > 0 ? '+' : '−') + money(Math.abs(Math.round(o.price_delta * 100)))"></span>
                                        </label>
                                    </template>
                                </div>
                            </fieldset>
                        </template>

                        <div class="mt-5">
                            <label class="text-sm font-semibold" for="line-note">{{ __('customer.note') }}</label>
                            <textarea id="line-note" x-model="sheet.note" rows="2" maxlength="200" placeholder="{{ __('customer.note_placeholder') }}" class="menu-card mt-1.5 w-full px-3 py-2 text-sm"></textarea>
                        </div>
                    </div>
                </div>
                <div class="menu-surface menu-line flex items-center gap-3 border-t p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                    <div class="menu-card flex items-center" role="group" aria-label="{{ __('customer.quantity') }}">
                        <button type="button" class="grid size-10 place-items-center text-xl" x-on:click="step(-1)" aria-label="{{ __('customer.decrease') }}" :disabled="sheet.qty <= 1">−</button>
                        <span class="tnum min-w-7 text-center font-semibold" x-text="sheet.qty" aria-live="polite"></span>
                        <button type="button" class="grid size-10 place-items-center text-xl" x-on:click="step(1)" aria-label="{{ __('customer.increase') }}">+</button>
                    </div>
                    <button type="button" class="menu-btn flex-1 justify-between !py-3" x-on:click="submitSheet()" :class="sheetValid ? '' : 'opacity-60'">
                        <span x-text="sheet.editKey ? @js(__('customer.update_cart')) : @js(__('customer.add_to_cart'))"></span>
                        <span class="tnum" x-text="money(sheetUnit * sheet.qty)"></span>
                    </button>
                </div>
            </div>
        </template>
    </div>

    {{-- Cart and checkout drawer --}}
    <div x-show="cartOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ __('customer.cart') }}" x-on:keydown.tab="trap($event)">
        <div class="absolute inset-0 bg-black/50" x-on:click="cartOpen = false" x-transition.opacity></div>
        <div class="menu-page relative flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden shadow-2xl" style="border-radius: min(var(--menu-radius) * 1.6, 1.75rem) min(var(--menu-radius) * 1.6, 1.75rem) 0 0">
            <div class="menu-line flex items-center justify-between gap-3 border-b px-5 py-4">
                <div class="flex min-w-0 items-center gap-2">
                    <button type="button" x-show="stage === 'checkout'" x-on:click="stage = 'cart'" class="menu-card grid size-9 shrink-0 place-items-center" aria-label="{{ __('orders.back') }}"><x-ui.icon name="chevron-right" size="5" class="rotate-180 rtl:rotate-0" /></button>
                    <div class="min-w-0"><h2 class="display text-xl font-bold" x-text="stage === 'cart' ? @js(__('customer.cart')) : @js(__('orders.checkout'))"></h2><p class="menu-muted text-xs" x-show="stage === 'cart' && cart.length">{{ __('customer.cart_hint') }}</p></div>
                </div>
                <button type="button" x-on:click="cartOpen = false" class="menu-card grid size-9 shrink-0 place-items-center" aria-label="{{ __('customer.close') }}"><x-ui.icon name="x" size="5" /></button>
            </div>
            @if ($currencies)<p class="menu-muted px-5 pt-2 text-xs" x-show="curObj && !curObj.base" x-cloak>{{ __('customer.currency_note', ['base' => $restaurant->currency_code]) }}</p>@endif

            {{-- Step 1: the cart --}}
            <div class="overflow-y-auto px-5" x-show="stage === 'cart'">
                <div x-show="cart.length === 0" class="py-12 text-center"><p class="font-semibold">{{ __('customer.cart_empty_title') }}</p><p class="menu-muted mt-1 text-sm">{{ __('customer.cart_empty_text') }}</p></div>
                <ul class="menu-divide">
                    <template x-for="(line, i) in cart" :key="line.key">
                        <li class="py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold" x-text="line.name"></p>
                                    <p class="menu-muted text-sm" x-show="line.labels.length" x-text="line.labels.join(', ')"></p>
                                    <p class="menu-muted text-sm italic" x-show="line.note" x-text="'“' + line.note + '”'"></p>
                                    <p class="mt-1 text-sm font-medium text-red-600" x-show="view(line, i).errors.length" x-text="errorText(view(line, i).errors)" role="alert"></p>
                                </div>
                                <p class="tnum shrink-0 font-semibold" x-text="view(line, i).total"></p>
                            </div>
                            <div class="mt-2.5 flex items-center justify-between">
                                <div class="menu-card flex items-center">
                                    <button type="button" class="grid size-9 place-items-center text-lg" x-on:click="bump(line, -1)" aria-label="{{ __('customer.decrease') }}">−</button>
                                    <span class="tnum min-w-6 text-center text-sm font-semibold" x-text="line.qty"></span>
                                    <button type="button" class="grid size-9 place-items-center text-lg" x-on:click="bump(line, 1)" aria-label="{{ __('customer.increase') }}">+</button>
                                </div>
                                <div class="flex gap-3 text-sm">
                                    <button type="button" class="font-medium underline underline-offset-2" x-on:click="editLine(line)">{{ __('customer.edit') }}</button>
                                    <button type="button" class="font-medium text-red-600 underline underline-offset-2" x-on:click="remove(line)">{{ __('customer.remove') }}</button>
                                </div>
                            </div>
                        </li>
                    </template>
                </ul>
                <div class="menu-line -mx-5 border-t px-5 py-4" x-show="cart.length > 0 && suggestions.length > 0">
                    <p class="mb-2 text-sm font-bold">{{ __('customer.goes_well') }}</p>
                    <div class="menu-scroll -mx-5 flex gap-2.5 overflow-x-auto px-5">
                        <template x-for="p in suggestions" :key="'s' + p.id">
                            <div class="menu-card flex w-52 shrink-0 items-center gap-2.5 p-2">
                                <div class="menu-img size-12 shrink-0 overflow-hidden rounded-lg"><img :src="pic(p)" alt="" loading="lazy" class="size-full object-cover"></div>
                                <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold" x-text="p.name"></p><p class="tnum text-xs font-bold" x-text="priceText(p)"></p></div>
                                <button type="button" class="menu-add !size-8" x-on:click="quickAdd(p, $event)" :aria-label="@js(__('customer.add')) + ' ' + p.name"><x-ui.icon name="plus" size="4" /></button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <div class="menu-surface menu-line space-y-3 border-t p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]" x-show="stage === 'cart' && cart.length > 0">
                <div class="flex items-baseline justify-between text-lg font-semibold"><span>{{ __('customer.subtotal') }}</span><span class="tnum" x-text="subtotal"></span></div>
                <p class="menu-muted text-xs" x-show="quoting">{{ __('customer.checking') }}</p>
                <p class="menu-muted text-xs" x-show="offline && !quoting">{{ __('customer.offline_total') }}</p>
                <p class="text-sm font-medium text-red-600" x-show="hasErrors" role="alert">{{ __('customer.fix_cart') }}</p>
                <p class="menu-card px-3 py-2 text-sm font-medium" x-show="!ord.accepting" x-text="ord.message" role="status"></p>
                <button type="button" class="menu-btn w-full !py-3.5 !text-base" :disabled="!ord.accepting || hasErrors || quoting" x-on:click="startCheckout()">{{ __('orders.checkout') }}</button>
            </div>

            {{-- Step 2: how and where --}}
            <div class="overflow-y-auto px-5 py-4" x-show="stage === 'checkout'" x-cloak>
                <fieldset>
                    <legend class="mb-2 font-semibold">{{ __('orders.how') }}</legend>
                    <div class="grid gap-2" :class="ord.types.length > 2 ? 'grid-cols-3' : (ord.types.length === 2 ? 'grid-cols-2' : 'grid-cols-1')">
                        @foreach (['dine_in' => 'qr', 'takeaway' => 'smartphone', 'delivery' => 'store', 'curbside' => 'car', 'room_service' => 'bed'] as $type => $icon)
                            <label x-show="ord.types.includes('{{ $type }}')" class="menu-card flex cursor-pointer flex-col items-center gap-1 px-2 py-3 text-center text-sm font-semibold" :style="form.type === '{{ $type }}' ? 'border-color: var(--menu-accent); box-shadow: 0 0 0 2px var(--menu-accent)' : ''">
                                <input type="radio" name="otype" value="{{ $type }}" x-model="form.type" class="sr-only">
                                <x-ui.icon name="{{ $icon }}" size="5" />{{ __('orders.type_'.$type) }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="mt-4 space-y-3">
                    <div x-show="form.type === 'dine_in' && !ord.table && ord.tables.length">
                        <label class="mb-1 block text-sm font-semibold" for="co-table">{{ __('orders.choose_table') }}</label>
                        <select id="co-table" x-model="form.table_id" class="menu-card w-full px-3 py-2.5"><template x-for="t in ord.tables" :key="t.id"><option :value="t.id" x-text="t.name"></option></template></select>
                        <p class="mt-1 text-sm text-red-600" x-show="errors.table_id" x-text="errors.table_id" role="alert"></p>
                    </div>
                    <p class="menu-card px-3 py-2.5 text-sm font-medium" x-show="form.type === 'dine_in' && ord.table"><x-ui.icon name="qr" size="4" class="me-1 inline" /><span x-text="ord.table ? ord.table.label : ''"></span></p>

                    <div x-show="needsContact || ord.requireName">
                        <label class="mb-1 block text-sm font-semibold" for="co-name">{{ __('orders.name') }}</label>
                        <input id="co-name" x-model="form.name" maxlength="80" autocomplete="name" class="menu-card w-full px-3 py-2.5">
                        <p class="mt-1 text-sm text-red-600" x-show="errors.name" x-text="errors.name" role="alert"></p>
                    </div>
                    <div x-show="needsContact">
                        <label class="mb-1 block text-sm font-semibold" for="co-phone">{{ __('orders.phone_label') }}</label>
                        <input id="co-phone" x-model="form.phone" type="tel" inputmode="tel" maxlength="40" autocomplete="tel" class="menu-card w-full px-3 py-2.5" dir="ltr">
                        <p class="mt-1 text-sm text-red-600" x-show="errors.phone" x-text="errors.phone" role="alert"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold" for="co-email">{{ __('orders.email_label') }}</label>
                        <input id="co-email" x-model="form.email" type="email" inputmode="email" maxlength="190" autocomplete="email" class="menu-card w-full px-3 py-2.5" dir="ltr">
                        <p class="menu-muted mt-1 text-xs" x-show="!errors.email">{{ __('orders.email_help') }}</p>
                        <p class="mt-1 text-sm text-red-600" x-show="errors.email" x-text="errors.email" role="alert"></p>
                        <label class="mt-2 flex items-start gap-2 text-sm" x-show="form.email.trim().length > 3" x-cloak>
                            <input type="checkbox" x-model="form.marketing" class="mt-0.5 size-4 shrink-0 accent-[var(--menu-accent)]">
                            <span>{{ __('marketing.opt_in', ['name' => $restaurant->name]) }}</span>
                        </label>
                    </div>
                    <div x-show="form.type === 'delivery' && ord.zones.length" x-cloak>
                        <label class="mb-1 block text-sm font-semibold" for="co-zone">{{ __('orders.zone_label') }}</label>
                        <select id="co-zone" x-model="form.zone" class="menu-card w-full px-3 py-2.5">
                            <option value="">{{ __('orders.zone_choose') }}</option>
                            <template x-for="z in ord.zones" :key="z.id"><option :value="z.id" x-text="z.name + ' · ' + z.fee + (z.min ? ' · ' + @js(__('orders.zone_min')) + ' ' + z.min : '')"></option></template>
                        </select>
                        <p class="mt-1 text-sm text-red-600" x-show="errors.zone" x-text="errors.zone" role="alert"></p>
                    </div>
                    <div x-show="form.type === 'curbside'" x-cloak>
                        <label class="mb-1 block text-sm font-semibold" for="co-vehicle">{{ __('orders.vehicle_label') }}</label>
                        <input id="co-vehicle" x-model="form.vehicle" maxlength="80" class="menu-card w-full px-3 py-2.5">
                        <p class="mt-1 text-sm text-red-600" x-show="errors.vehicle" x-text="errors.vehicle" role="alert"></p>
                    </div>
                    <div x-show="form.type === 'room_service'" x-cloak>
                        <label class="mb-1 block text-sm font-semibold" for="co-room">{{ __('orders.room_label') }}</label>
                        <input id="co-room" x-model="form.room" maxlength="30" class="menu-card w-full px-3 py-2.5">
                        <p class="mt-1 text-sm text-red-600" x-show="errors.room" x-text="errors.room" role="alert"></p>
                    </div>
                    <div x-show="ord.schedule" x-cloak>
                        <p class="mb-1 text-sm font-semibold">{{ __('orders.schedule_when') }}</p>
                        <div class="flex gap-2">
                            <label class="menu-card flex flex-1 cursor-pointer items-center justify-center px-3 py-2.5 text-sm font-semibold" :style="!form.later ? 'border-color: var(--menu-accent)' : ''"><input type="radio" name="when-mode" class="sr-only" :checked="!form.later" x-on:change="form.later = false">{{ __('orders.schedule_asap') }}</label>
                            <label class="menu-card flex flex-1 cursor-pointer items-center justify-center px-3 py-2.5 text-sm font-semibold" :style="form.later ? 'border-color: var(--menu-accent)' : ''"><input type="radio" name="when-mode" class="sr-only" :checked="form.later" x-on:change="form.later = true">{{ __('orders.schedule_later') }}</label>
                        </div>
                        <input type="datetime-local" x-show="form.later" x-cloak x-model="form.when" :min="whenMin" :max="whenMax" class="menu-card mt-2 w-full px-3 py-2.5" aria-label="{{ __('orders.schedule_later') }}">
                        <p class="mt-1 text-sm text-red-600" x-show="errors.when" x-text="errors.when" role="alert"></p>
                    </div>
                    <div x-show="ord.notify.length" x-cloak>
                        <label class="mb-1 block text-sm font-semibold" for="co-notify">{{ __('orders.notify_me') }}</label>
                        <select id="co-notify" x-model="form.notify" class="menu-card w-full px-3 py-2.5">
                            <option value="">{{ __('orders.notify_none') }}</option>
                            <template x-for="n in ord.notify" :key="n"><option :value="n" x-text="notifyLabels[n]"></option></template>
                        </select>
                    </div>
                    <div x-show="form.type === 'delivery'">
                        <label class="mb-1 block text-sm font-semibold" for="co-address">{{ __('orders.address_label') }}</label>
                        <textarea id="co-address" x-model="form.address" rows="2" maxlength="255" autocomplete="street-address" class="menu-card w-full px-3 py-2.5"></textarea>
                        <p class="mt-1 text-sm text-red-600" x-show="errors.address" x-text="errors.address" role="alert"></p>
                        <p class="menu-muted mt-1 text-xs" x-show="ord.deliveryMin">{{ __('orders.minimum_note', ['amount' => '']) }}<span x-text="ord.deliveryMin"></span></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold" for="co-note">{{ __('orders.note_label') }}</label>
                        <textarea id="co-note" x-model="form.note" rows="2" maxlength="300" class="menu-card w-full px-3 py-2.5"></textarea>
                    </div>
                    <fieldset x-show="ord.payments.length > 0">
                        <legend class="mb-1.5 text-sm font-semibold">{{ __('orders.pay_how') }}</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach (['cash', 'card', 'online'] as $m)
                                <label x-show="ord.payments.includes('{{ $m }}')" class="menu-card flex flex-1 cursor-pointer items-center justify-center gap-2 px-3 py-2.5 text-sm font-semibold" :style="form.payment === '{{ $m }}' ? 'border-color: var(--menu-accent); box-shadow: 0 0 0 2px var(--menu-accent)' : ''">
                                    <input type="radio" name="pay" value="{{ $m }}" x-model="form.payment" class="sr-only"><x-ui.icon name="{{ ['cash' => 'wallet', 'card' => 'credit-card', 'online' => 'smartphone'][$m] }}" size="4" />{{ __('orders.pay_'.$m) }}
                                </label>
                            @endforeach
                        </div>
                        <p class="menu-muted mt-1.5 text-xs" x-text="form.payment === 'online' ? @js(__('orders.pay_on_spot_online')) : @js(['dine_in' => __('orders.pay_on_spot_dine_in'), 'takeaway' => __('orders.pay_on_spot_takeaway'), 'delivery' => __('orders.pay_on_spot_delivery'), 'curbside' => __('orders.pay_on_spot_curbside'), 'room_service' => __('orders.pay_on_spot_room_service')])[form.type]"></p>
                    </fieldset>
                </div>

                <div class="mt-5" x-show="ord.promos" x-cloak>
                    <label class="mb-1 block text-sm font-semibold" for="co-promo">{{ __('marketing.promo_field') }}</label>
                    <div class="flex gap-2" x-show="!(promo && promo.valid)">
                        <input id="co-promo" x-model="promoInput" x-on:keydown.enter.prevent="applyPromo()" maxlength="40" autocomplete="off" autocapitalize="characters" spellcheck="false" class="menu-card min-w-0 flex-1 px-3 py-2.5 uppercase" dir="ltr">
                        <button type="button" class="menu-card px-4 text-sm font-semibold" x-on:click="applyPromo()" :disabled="!promoInput.trim()">{{ __('marketing.promo_apply') }}</button>
                    </div>
                    <p class="mt-1.5 text-sm text-red-600" x-show="promo && !promo.valid" x-text="promo ? promo.message : ''" role="alert"></p>
                    <p class="mt-1.5 flex items-center justify-between gap-2 text-sm font-medium menu-accent-text" x-show="promo && promo.valid"><span x-text="promo ? promo.message : ''"></span><button type="button" class="menu-muted text-xs underline" x-on:click="clearPromo()">{{ __('marketing.promo_remove') }}</button></p>
                </div>

                <p class="menu-muted mt-4 text-sm" x-show="ord.wait && !form.later" x-text="@js(__('orders.wait_now', ['minutes' => ':n'])).replace(':n', ord.wait)"></p>
                <p class="menu-muted mt-1 text-xs" x-show="ord.maxItems > 0" x-text="@js(__('orders.max_items_note', ['count' => ':n'])).replace(':n', ord.maxItems)"></p>
                <p class="mt-1 text-sm text-red-600" x-show="overMax" x-text="@js(__('orders.max_items_over', ['count' => ':n'])).replace(':n', ord.maxItems)" role="alert"></p>
                <dl class="menu-line mt-5 space-y-1.5 border-t pt-4 text-sm tnum">
                    <div class="flex justify-between"><dt class="menu-muted">{{ __('customer.subtotal') }}</dt><dd x-text="totals ? totals.subtotal : subtotal"></dd></div>
                    <div class="flex justify-between font-medium menu-accent-text" x-show="totals && totals.raw.discount > 0"><dt>{{ __('marketing.promo_discount') }}</dt><dd x-text="totals ? '−' + totals.discount : ''"></dd></div>
                    <div class="flex justify-between" x-show="totals && totals.raw.service > 0"><dt class="menu-muted">{{ __('orders.service') }}</dt><dd x-text="totals ? totals.service : ''"></dd></div>
                    <div class="flex justify-between" x-show="totals && totals.raw.packaging > 0"><dt class="menu-muted">{{ __('orders.packaging') }}</dt><dd x-text="totals ? totals.packaging : ''"></dd></div>
                    <div class="flex justify-between" x-show="totals && totals.raw.delivery > 0"><dt class="menu-muted">{{ __('orders.delivery_fee') }}</dt><dd x-text="totals ? totals.delivery : ''"></dd></div>
                    <div class="flex justify-between" x-show="totals && totals.raw.tax > 0"><dt class="menu-muted">{{ __('orders.tax') }}<span x-show="ord.taxIncluded"> ({{ __('orders.tax_included') }})</span></dt><dd x-text="totals ? totals.tax : ''"></dd></div>
                </dl>
            </div>
            <div class="menu-surface menu-line space-y-3 border-t p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]" x-show="stage === 'checkout'" x-cloak>
                <p class="text-sm font-medium text-red-600" x-show="formError" x-text="formError" role="alert"></p>
                <button type="button" class="menu-btn w-full justify-between !py-3.5 !text-base" :disabled="submitting || quoting" x-on:click="submit()">
                    <span x-text="submitting ? @js(__('orders.placing')) : @js(__('orders.place'))"></span>
                    <span class="tnum" x-text="totals ? totals.total : subtotal"></span>
                </button>
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>
