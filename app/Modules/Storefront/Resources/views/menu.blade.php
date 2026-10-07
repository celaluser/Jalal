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
        'storageKey' => 'qrmenu.cart.'.$restaurant->id,
        'diet' => collect($dietary)->mapWithKeys(fn ($d) => [$d => __('menu.diet.'.$d)])->all(),
        'allergen' => collect($allergens)->mapWithKeys(fn ($a) => [$a => __('menu.allergen.'.$a)])->all(),
        't' => collect(['sold_out', 'unavailable', 'option_required', 'option_unavailable', 'invalid_option', 'too_many_options', 'quantity'])->mapWithKeys(fn ($k) => [$k => __('customer.error_'.$k)])->all(),
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
    {{-- Header --}}
    <header class="mx-auto max-w-3xl px-4 pt-5">
        <div class="flex items-center gap-3">
            @if ($logo)<img src="{{ $logo }}" alt="" class="menu-radius size-12 shrink-0 object-cover">
            @else<span class="menu-accent menu-radius grid size-12 shrink-0 place-items-center text-lg font-bold">{{ mb_strtoupper(mb_substr($restaurant->name, 0, 1)) }}</span>@endif
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-xl font-semibold leading-tight">{{ $restaurant->name }}</h1>
                @if ($table)<p class="menu-muted text-sm">{{ __('customer.table', ['name' => $table['name']]) }}</p>
                @elseif ($restaurant->city)<p class="menu-muted truncate text-sm">{{ $restaurant->city }}</p>@endif
            </div>
            @if (count($languages) > 1)
                <nav aria-label="{{ __('customer.language') }}" class="flex gap-1">
                    @foreach ($languages as $code => $name)
                        <a href="?lang={{ $code }}" hreflang="{{ $code }}" lang="{{ $code }}" class="menu-chip !px-2.5 !py-1 uppercase" @if ($code === $locale) aria-current="true" @endif title="{{ $name }}">{{ $code }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
        @if (session('table_invalid'))<p class="menu-card menu-muted mt-3 px-3 py-2 text-sm" role="status">{{ __('customer.table_invalid') }}</p>@endif

        {{-- Search and filters --}}
        <div class="mt-4 flex gap-2">
            <div class="relative flex-1">
                <x-ui.icon name="search" size="4" class="menu-muted pointer-events-none absolute start-3 top-1/2 -translate-y-1/2" />
                <input type="search" x-model="q" placeholder="{{ __('customer.search_placeholder') }}" aria-label="{{ __('customer.search') }}" class="menu-card w-full py-2.5 ps-9 pe-3 text-sm placeholder:opacity-60">
            </div>
            <button type="button" class="menu-card relative grid size-[2.6rem] shrink-0 place-items-center" x-on:click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen" aria-label="{{ __('customer.filters') }}">
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
    </header>

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
    <main class="mx-auto max-w-3xl px-4 pt-4">
        @if (! $tree)
            <div class="menu-card px-6 py-14 text-center"><p class="text-lg font-semibold">{{ __('customer.empty_title') }}</p><p class="menu-muted mt-1">{{ __('customer.empty_text') }}</p></div>
        @else
            <template x-for="c in visible" :key="c.id">
                <section class="mb-8 scroll-mt-16" :id="'cat-' + c.id" :data-cat="c.id" :aria-label="c.name">
                    <h2 class="mb-1 text-lg font-semibold" x-text="c.name"></h2>
                    <p class="menu-muted mb-3 text-sm" x-show="c.description" x-text="c.description"></p>
                    <div class="{{ $layout === 'grid' ? 'grid grid-cols-2 gap-3' : 'space-y-3' }}">
                        <template x-for="p in c.products" :key="p.id">
                            <article class="menu-card relative overflow-hidden {{ $layout === 'list' ? 'flex gap-3 p-3' : ($layout === 'grid' ? 'flex flex-col' : '') }}" :class="p.available ? '' : 'opacity-60'">
                                @if ($settings['show_images'] && $layout !== 'list')
                                    <button type="button" x-show="p.image" x-on:click="open(p)" tabindex="-1" aria-hidden="true" class="block w-full {{ $layout === 'grid' ? 'aspect-[4/3]' : 'aspect-[16/9]' }}" style="background: var(--menu-line)">
                                        <img x-show="p.image" :src="p.image" alt="" loading="lazy" class="size-full object-cover">
                                    </button>
                                @endif
                                <div class="{{ $layout === 'list' ? 'min-w-0 flex-1' : 'flex flex-1 flex-col p-3' }}">
                                    <button type="button" x-on:click="open(p)" class="block w-full text-start">
                                        <span class="flex items-start justify-between gap-2">
                                            <span class="font-semibold leading-snug" x-text="p.name"></span>
                                        </span>
                                        <span class="menu-muted mt-0.5 line-clamp-2 block text-sm" x-show="p.description" x-text="p.description"></span>
                                    </button>
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs" x-show="p.featured || !p.available || p.dietary.length">
                                        <span class="menu-accent rounded-full px-2 py-0.5 font-semibold" x-show="p.featured && p.available">{{ __('customer.featured') }}</span>
                                        <span class="rounded-full border px-2 py-0.5 font-semibold menu-line" x-show="!p.available">{{ __('customer.sold_out') }}</span>
                                        <template x-for="d in p.dietary" :key="d"><span class="menu-muted rounded-full border px-2 py-0.5 menu-line" x-text="cfg_diet[d]"></span></template>
                                    </div>
                                    <div class="mt-auto flex items-center justify-between gap-2 pt-3">
                                        <p class="tnum"><span class="font-semibold" x-text="money(cents(p.price))"></span> <s class="menu-muted ms-1 text-sm" x-show="p.compare_price" x-text="p.compare_price ? money(cents(p.compare_price)) : ''"></s></p>
                                        <button type="button" class="menu-btn !px-3 !py-1.5 !text-sm" :disabled="!p.available" x-on:click="quickAdd(p)" :aria-label="(p.option_groups.length ? @js(__('customer.choose')) : @js(__('customer.add'))) + ' ' + p.name">
                                            <x-ui.icon name="plus" size="4" /><span x-text="p.option_groups.length ? @js(__('customer.choose')) : @js(__('customer.add'))"></span>
                                        </button>
                                    </div>
                                </div>
                                @if ($settings['show_images'] && $layout === 'list')
                                    <button type="button" x-show="p.image" x-on:click="open(p)" tabindex="-1" aria-hidden="true" class="menu-radius order-last size-24 shrink-0 self-start overflow-hidden" style="background: var(--menu-line)">
                                        <img x-show="p.image" :src="p.image" alt="" loading="lazy" class="size-full object-cover">
                                    </button>
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
        <button type="button" class="menu-btn mx-auto flex w-full max-w-3xl justify-between !py-3.5 shadow-lg" x-on:click="cartOpen = true">
            <span class="flex items-center gap-2"><span class="grid min-w-6 place-items-center rounded-full bg-black/15 px-1.5 text-sm font-bold" x-text="count"></span>{{ __('customer.view_cart') }}</span>
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
                    <div class="aspect-[16/10] w-full" style="background: var(--menu-line)" x-show="sheet.product.image"><img :src="sheet.product.image" alt="" class="size-full object-cover"></div>
                    <div class="p-5">
                        <h2 class="pe-10 text-xl font-semibold" x-text="sheet.product.name"></h2>
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

    {{-- Cart drawer --}}
    <div x-show="cartOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ __('customer.cart') }}" x-on:keydown.tab="trap($event)">
        <div class="absolute inset-0 bg-black/50" x-on:click="cartOpen = false" x-transition.opacity></div>
        <div class="menu-page relative flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden shadow-2xl" style="border-radius: min(var(--menu-radius) * 1.6, 1.75rem) min(var(--menu-radius) * 1.6, 1.75rem) 0 0">
            <div class="menu-line flex items-center justify-between border-b px-5 py-4">
                <h2 class="text-lg font-semibold">{{ __('customer.cart') }}</h2>
                <button type="button" x-on:click="cartOpen = false" class="menu-card grid size-9 place-items-center" aria-label="{{ __('customer.close') }}"><x-ui.icon name="x" size="5" /></button>
            </div>
            <div class="overflow-y-auto px-5">
                <div x-show="cart.length === 0" class="py-12 text-center"><p class="font-semibold">{{ __('customer.cart_empty_title') }}</p><p class="menu-muted mt-1 text-sm">{{ __('customer.cart_empty_text') }}</p></div>
                <ul class="divide-y menu-line">
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
            </div>
            <div class="menu-surface menu-line space-y-3 border-t p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]" x-show="cart.length > 0">
                <div class="flex items-baseline justify-between text-lg font-semibold"><span>{{ __('customer.subtotal') }}</span><span class="tnum" x-text="subtotal"></span></div>
                <p class="menu-muted text-xs" x-show="quoting">{{ __('customer.checking') }}</p>
                <p class="menu-muted text-xs" x-show="offline && !quoting">{{ __('customer.offline_total') }}</p>
                <p class="text-sm font-medium text-red-600" x-show="hasErrors" role="alert">{{ __('customer.fix_cart') }}</p>
                {{-- Enabled when ordering ships (the cart above already prices on the server) --}}
                <button type="button" class="menu-btn w-full !py-3.5" disabled>{{ __('customer.place_order') }}</button>
                <p class="menu-muted text-center text-xs">{{ __('customer.ordering_soon') }}</p>
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>
