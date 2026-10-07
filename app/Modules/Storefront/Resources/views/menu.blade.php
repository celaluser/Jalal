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
        'storageKey' => 'qrmenu.cart.'.$restaurant->id,
        'diet' => collect($dietary)->mapWithKeys(fn ($d) => [$d => __('menu.diet.'.$d)])->all(),
        'allergen' => collect($allergens)->mapWithKeys(fn ($a) => [$a => __('menu.allergen.'.$a)])->all(),
        't' => collect(['sold_out', 'unavailable', 'option_required', 'option_unavailable', 'invalid_option', 'too_many_options', 'quantity'])->mapWithKeys(fn ($k) => [$k => __('customer.error_'.$k)])->all()
            + collect(['table_required', 'name_required', 'phone_required', 'address_required', 'email_invalid'])->mapWithKeys(fn ($k) => [$k => __('orders.error_'.$k)])->all()
            + ['too_many' => __('customer.too_many'), 'generic_error' => __('customer.generic_error')],
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
    @if ($noindex)<meta name="robots" content="noindex">@endif
    <style>{!! $themeCss !!}</style>
    @vite(['resources/css/app.css', 'resources/js/storefront.js'])
    @livewireStyles
</head>
<body class="menu-page min-h-screen pb-28" x-data="storefront(@js($config))" x-on:keydown.escape.window="closeAll()">
    {{-- Hero: brand colour, restaurant, table and language --}}
    <header class="menu-hero relative overflow-hidden">
        <div class="mx-auto max-w-3xl px-4 pb-14 pt-5">
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
                @if (count($languages) > 1)
                    <nav aria-label="{{ __('customer.language') }}" class="flex shrink-0 gap-1">
                        @foreach ($languages as $code => $name)
                            <a href="?lang={{ $code }}" hreflang="{{ $code }}" lang="{{ $code }}" class="rounded-full px-2.5 py-1 text-xs font-bold uppercase {{ $code === $locale ? 'bg-white text-[#0f1115]' : 'bg-black/15' }}" @if ($code === $locale) aria-current="true" @endif title="{{ $name }}">{{ $code }}</a>
                        @endforeach
                    </nav>
                @endif
            </div>
            <p class="display menu-rise mt-6 max-w-xs text-3xl font-bold leading-[1.1]">{{ __('customer.hero_title') }}</p>
            <p class="mt-2 max-w-xs text-sm opacity-90">{{ $table ? __('customer.hero_table') : __('customer.hero_text') }}</p>
            {{-- Dish illustrations / photos peeking in from the corner --}}
            <div class="pointer-events-none absolute -end-6 bottom-2 hidden gap-[-1rem] sm:flex" aria-hidden="true">
                @foreach (collect($tree)->flatMap(fn ($c) => $c['products'])->take(3) as $i => $p)
                    <img src="{{ $p['image'] ?: $p['art'] }}" alt="" class="size-24 rounded-full border-4 border-white/70 object-cover shadow-xl {{ $i ? '-ms-6' : '' }}">
                @endforeach
            </div>
        </div>
    </header>

    <div class="mx-auto -mt-7 max-w-3xl px-4">
        @if (session('table_invalid'))<p class="menu-card menu-muted mb-3 px-3 py-2 text-sm" role="status">{{ __('customer.table_invalid') }}</p>@endif

        {{-- Search and filters --}}
        <div class="flex gap-2">
            <div class="menu-card relative flex-1 shadow-lg">
                <x-ui.icon name="search" size="4" class="menu-muted pointer-events-none absolute start-3.5 top-1/2 -translate-y-1/2" />
                <input type="search" x-model="q" placeholder="{{ __('customer.search_placeholder') }}" aria-label="{{ __('customer.search') }}" class="w-full bg-transparent py-3 ps-10 pe-3 text-sm placeholder:opacity-60 focus:outline-none">
            </div>
            <button type="button" class="menu-card relative grid size-[2.9rem] shrink-0 place-items-center shadow-lg" x-on:click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen" aria-label="{{ __('customer.filters') }}">
                <x-ui.icon name="sliders" size="5" />
                <span x-show="diet.length + avoid.length" x-cloak class="menu-accent absolute -end-1 -top-1 grid size-4 place-items-center rounded-full text-[10px] font-bold" x-text="diet.length + avoid.length"></span>
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
            <button type="button" class="text-sm font-medium underline underline-offset-2" x-show="hasFilters" x-on:click="resetFilters()">{{ __('customer.reset_filters') }}</button>
        </div>
    </div>

    {{-- Most loved --}}
    <section class="mx-auto mt-6 max-w-3xl" x-show="featured.length > 0 && !hasFilters" x-cloak aria-label="{{ __('customer.most_loved') }}">
        <h2 class="display mb-3 flex items-center gap-2 px-4 text-xl font-bold"><x-ui.icon name="sparkles" size="5" class="menu-accent-text" />{{ __('customer.most_loved') }}</h2>
        <div class="menu-scroll menu-snap flex gap-3 overflow-x-auto px-4 pb-2">
            <template x-for="p in featured" :key="'f' + p.id">
                <article class="menu-card relative w-44 shrink-0 overflow-hidden sm:w-52">
                    <button type="button" class="menu-img block aspect-[4/3] w-full overflow-hidden" x-on:click="open(p)" tabindex="-1" aria-hidden="true"><img :src="pic(p)" alt="" loading="lazy" class="size-full object-cover"></button>
                    <div class="p-3">
                        <button type="button" class="block w-full truncate text-start font-semibold" x-on:click="open(p)" x-text="p.name"></button>
                        <p class="mt-1 flex items-center justify-between"><span class="tnum font-bold" x-text="money(cents(p.price))"></span>
                            <button type="button" class="menu-add !size-9" x-on:click="quickAdd(p, $event)" :aria-label="(p.option_groups.length ? @js(__('customer.choose')) : @js(__('customer.add'))) + ' ' + p.name"><x-ui.icon name="plus" size="5" /></button></p>
                    </div>
                </article>
            </template>
        </div>
    </section>

    {{-- Category tabs --}}
    @if ($tree)
        <nav class="menu-page menu-line sticky top-0 z-20 mt-3 border-b" aria-label="{{ __('menu.categories') }}">
            <div class="menu-scroll mx-auto flex max-w-3xl gap-2 overflow-x-auto px-4 py-2.5" x-show="visible.length > 0">
                <template x-for="c in visible" :key="c.id">
                    <button type="button" class="menu-chip" x-bind:data-tab="c.id" :aria-current="active === c.id" x-on:click="goTo(c.id)" x-text="c.name"></button>
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
                    <h2 class="display mb-1 text-xl font-bold" x-text="c.name"></h2>
                    <p class="menu-muted mb-3 text-sm" x-show="c.description" x-text="c.description"></p>
                    <div class="{{ $layout === 'grid' ? 'grid grid-cols-2 gap-3' : 'space-y-3' }}">
                        <template x-for="p in c.products" :key="p.id">
                            <article class="menu-card relative overflow-hidden {{ $layout === 'list' ? 'flex gap-3 p-3' : 'flex flex-col' }}" :class="p.available ? '' : 'opacity-60'">
                                @if ($settings['show_images'] && $layout !== 'list')
                                    <div class="menu-img relative w-full overflow-hidden {{ $layout === 'grid' ? 'aspect-[4/3]' : 'aspect-[16/9]' }}">
                                        <button type="button" x-on:click="open(p)" tabindex="-1" aria-hidden="true" class="block size-full"><img :src="pic(p)" alt="" loading="lazy" class="size-full object-cover"></button>
                                        <span class="menu-accent absolute start-2.5 top-2.5 rounded-full px-2.5 py-0.5 text-xs font-bold shadow" x-show="p.featured && p.available">{{ __('customer.featured') }}</span>
                                        <span class="tnum menu-surface absolute bottom-2.5 start-2.5 rounded-full px-2.5 py-1 text-sm font-bold shadow" x-text="money(cents(p.price))"></span>
                                        <button type="button" class="menu-add absolute bottom-2.5 end-2.5" :disabled="!p.available" x-on:click="quickAdd(p, $event)" :aria-label="(p.option_groups.length ? @js(__('customer.choose')) : @js(__('customer.add'))) + ' ' + p.name"><x-ui.icon name="plus" size="5" /></button>
                                    </div>
                                @endif
                                <div class="{{ $layout === 'list' ? 'flex min-w-0 flex-1 flex-col' : 'flex flex-1 flex-col p-3.5' }}">
                                    <button type="button" x-on:click="open(p)" class="block w-full text-start">
                                        <span class="block text-[1.05rem] font-bold leading-snug" x-text="p.name"></span>
                                        <span class="menu-muted mt-1 line-clamp-2 block text-sm leading-snug" x-show="p.description" x-text="p.description"></span>
                                    </button>
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs" x-show="!p.available || p.dietary.length || (p.featured && {{ $layout === 'list' || ! $settings['show_images'] ? 'true' : 'false' }})">
                                        <span class="menu-accent rounded-full px-2 py-0.5 font-semibold" x-show="p.featured && p.available && {{ $layout === 'list' || ! $settings['show_images'] ? 'true' : 'false' }}">{{ __('customer.featured') }}</span>
                                        <span class="rounded-full border px-2 py-0.5 font-semibold menu-line" x-show="!p.available">{{ __('customer.sold_out') }}</span>
                                        <template x-for="d in p.dietary" :key="d"><span class="menu-muted rounded-full border px-2 py-0.5 menu-line" x-text="cfg_diet[d]"></span></template>
                                    </div>
                                    @if ($layout === 'list' || ! $settings['show_images'])
                                        <div class="mt-auto flex items-center justify-between gap-2 pt-3">
                                            <p class="tnum"><span class="text-lg font-bold" x-text="money(cents(p.price))"></span> <s class="menu-muted ms-1 text-sm" x-show="p.compare_price" x-text="p.compare_price ? money(cents(p.compare_price)) : ''"></s></p>
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
                    <div class="menu-img aspect-[16/10] w-full"><img :src="pic(sheet.product)" alt="" class="size-full object-cover"></div>
                    <div class="p-5">
                        <h2 class="display pe-10 text-2xl font-bold leading-tight" x-text="sheet.product.name"></h2>
                        <p class="tnum mt-1 text-lg font-bold"><span x-text="money(cents(sheet.product.price))"></span> <s class="menu-muted ms-1 text-sm font-normal" x-show="sheet.product.compare_price" x-text="sheet.product.compare_price ? money(cents(sheet.product.compare_price)) : ''"></s></p>
                        <p class="menu-muted mt-1.5 whitespace-pre-line" x-show="sheet.product.description" x-text="sheet.product.description"></p>
                        <p class="menu-muted mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm" x-show="sheet.product.calories || sheet.product.prep_minutes">
                            <span x-show="sheet.product.calories" x-text="@js(__('customer.calories', ['count' => '__'])).replace('__', sheet.product.calories)"></span>
                            <span x-show="sheet.product.prep_minutes" x-text="@js(__('customer.minutes', ['count' => '__'])).replace('__', sheet.product.prep_minutes)"></span>
                        </p>
                        <div class="mt-3 flex flex-wrap gap-1.5" x-show="sheet.product.dietary.length">
                            <template x-for="d in sheet.product.dietary" :key="d"><span class="menu-chip !py-0.5" x-text="cfg_diet[d]"></span></template>
                        </div>
                        <p class="mt-3 text-sm" x-show="sheet.product.allergens.length"><span class="font-semibold">{{ __('customer.contains') }}:</span> <span class="menu-muted" x-text="sheet.product.allergens.map((a) => cfg_allergen[a]).join(', ')"></span></p>

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
                                <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold" x-text="p.name"></p><p class="tnum text-xs font-bold" x-text="money(cents(p.price))"></p></div>
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
                        @foreach (['dine_in' => 'qr', 'takeaway' => 'smartphone', 'delivery' => 'store'] as $type => $icon)
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
                        <div class="flex gap-2">
                            @foreach (['cash', 'card'] as $m)
                                <label x-show="ord.payments.includes('{{ $m }}')" class="menu-card flex flex-1 cursor-pointer items-center justify-center gap-2 px-3 py-2.5 text-sm font-semibold" :style="form.payment === '{{ $m }}' ? 'border-color: var(--menu-accent); box-shadow: 0 0 0 2px var(--menu-accent)' : ''">
                                    <input type="radio" name="pay" value="{{ $m }}" x-model="form.payment" class="sr-only"><x-ui.icon name="{{ $m === 'cash' ? 'wallet' : 'credit-card' }}" size="4" />{{ __('orders.pay_'.$m) }}
                                </label>
                            @endforeach
                        </div>
                        <p class="menu-muted mt-1.5 text-xs" x-text="@js(['dine_in' => __('orders.pay_on_spot_dine_in'), 'takeaway' => __('orders.pay_on_spot_takeaway'), 'delivery' => __('orders.pay_on_spot_delivery')])[form.type]"></p>
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

                <dl class="menu-line mt-5 space-y-1.5 border-t pt-4 text-sm tnum">
                    <div class="flex justify-between"><dt class="menu-muted">{{ __('customer.subtotal') }}</dt><dd x-text="totals ? totals.subtotal : subtotal"></dd></div>
                    <div class="flex justify-between font-medium menu-accent-text" x-show="totals && totals.raw.discount > 0"><dt>{{ __('marketing.promo_discount') }}</dt><dd x-text="totals ? '−' + totals.discount : ''"></dd></div>
                    <div class="flex justify-between" x-show="totals && totals.raw.service > 0"><dt class="menu-muted">{{ __('orders.service') }}</dt><dd x-text="totals ? totals.service : ''"></dd></div>
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
