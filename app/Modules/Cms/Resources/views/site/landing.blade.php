@php
    $seoTitle = platform_setting('seo.meta_title');
    $icons = ['qr', 'zap', 'languages', 'sparkles', 'smartphone', 'shield', 'globe', 'receipt'];
    $primaryUrl = $registration ? route('register') : route('login');
    // Look of the top of the page, chosen by the platform owner: aurora (default), midnight (dark hero) or clean (centred, no phone).
    $theme = in_array($t = platform_setting('site.landing_theme'), ['aurora', 'midnight', 'clean'], true) ? $t : 'aurora';
    $dark = $theme === 'midnight';
    $mutedCls = $dark ? 'text-ink-300' : 'text-muted';
@endphp
<x-layouts.site :title="$seoTitle" :description="$c['hero']['subtitle']">
    {{-- Hero --------------------------------------------------------------- --}}
    <section @class(['relative overflow-hidden', 'bg-ink-950 text-white' => $dark]) data-landing-theme="{{ $theme }}">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-[34rem] bg-[radial-gradient(60%_60%_at_70%_0%,color-mix(in_oklab,var(--color-brand-400)_28%,transparent),transparent)]"></div>
        <x-ui.qr-pattern class="pointer-events-none absolute -start-24 top-24 hidden size-[26rem] text-ink-900/[0.022] dark:text-white/[0.025] lg:block" :cells="25" :seed="4" />
        <div @class(['relative mx-auto grid max-w-6xl items-center gap-14 px-4 pb-20 pt-14 sm:px-6 sm:pt-20 lg:pb-28', 'lg:grid-cols-[1.05fr_0.95fr]' => $theme !== 'clean', 'max-w-3xl text-center' => $theme === 'clean'])>
            <div class="rise">
                <span class="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3 py-1 text-xs font-semibold shadow-sm"><span class="size-1.5 rounded-full bg-accent-500"></span>{{ __('site.hero.eyebrow') }}</span>
                <h1 class="display mt-6 text-[2.75rem] font-semibold leading-[1.02] sm:text-6xl lg:text-[4.25rem]">{{ $c['hero']['title'] }}</h1>
                <p @class(['mt-6 max-w-xl text-lg leading-relaxed', $mutedCls, 'mx-auto' => $theme === 'clean'])>{{ $c['hero']['subtitle'] }}</p>
                <div @class(['mt-9 flex flex-wrap gap-3', 'justify-center' => $theme === 'clean'])>
                    <a href="{{ $primaryUrl }}" class="btn btn-primary btn-lg">{{ $c['hero']['cta_label'] }}<x-ui.icon name="arrow-right" size="5" class="rtl:rotate-180" /></a>
                    @if (! empty($c['hero']['secondary_label']))<a href="#pricing" class="btn btn-secondary btn-lg">{{ $c['hero']['secondary_label'] }}</a>@endif
                </div>
                <ul @class(['mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm', $mutedCls, 'justify-center' => $theme === 'clean'])>
                    @foreach (['no_card', 'cancel', 'setup'] as $point)<li class="flex items-center gap-2"><x-ui.icon name="check" size="4" class="text-accent-600" />{{ __('site.hero.'.$point) }}</li>@endforeach
                </ul>
            </div>

            {{-- Decorative product preview --}}
            @if ($theme !== 'clean')
            <div class="relative mx-auto w-full max-w-md" aria-hidden="true">
                <div class="absolute inset-6 -z-10 rounded-[3rem] bg-brand-500/20 blur-3xl"></div>
                <div class="relative mx-auto w-[17.5rem] rounded-[2.6rem] border-[9px] border-ink-950 bg-surface shadow-pop dark:border-ink-800">
                    <div class="px-4 pb-4 pt-7">
                        <div class="flex items-center justify-between"><div><p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-muted">{{ __('site.mock.table') }}</p><p class="display text-lg font-semibold leading-tight">{{ __('site.mock.restaurant') }}</p></div><span class="grid size-8 place-items-center rounded-full bg-surface-2"><x-ui.icon name="search" size="4" /></span></div>
                        <div class="mt-3 flex gap-1.5 text-[11px] font-semibold">
                            <span class="rounded-full bg-ink-950 px-3 py-1 text-white dark:bg-white dark:text-ink-950">{{ __('site.mock.cat1') }}</span><span class="rounded-full bg-surface-2 px-3 py-1 text-muted">{{ __('site.mock.cat2') }}</span><span class="rounded-full bg-surface-2 px-3 py-1 text-muted">{{ __('site.mock.cat3') }}</span>
                        </div>
                        <ul class="mt-4 space-y-3">
                            @foreach ([['d1', 'from-amber-300 to-orange-500', '12.50'], ['d2', 'from-lime-300 to-emerald-500', '9.00'], ['d3', 'from-rose-300 to-red-500', '14.00']] as [$key, $grad, $price])
                                <li class="flex items-center gap-3 rounded-2xl border border-line p-2">
                                    <span class="size-12 shrink-0 rounded-xl bg-gradient-to-br {{ $grad }}"></span>
                                    <span class="min-w-0 flex-1"><span class="block truncate text-[13px] font-semibold">{{ __('site.mock.'.$key) }}</span><span class="tnum block text-xs text-muted">${{ $price }}</span></span>
                                    <span class="grid size-7 place-items-center rounded-full bg-brand-500 text-ink-950"><x-ui.icon name="plus" size="4" /></span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-4 flex items-center justify-between rounded-2xl bg-ink-950 px-4 py-3 text-xs font-semibold text-white dark:bg-white dark:text-ink-950"><span>{{ __('site.mock.view_order') }}</span><span class="tnum">$26.50</span></div>
                    </div>
                </div>
                <div class="float-y absolute -end-2 top-10 flex items-center gap-3 rounded-2xl border border-line bg-surface px-4 py-3 shadow-pop sm:-end-8">
                    <span class="grid size-9 place-items-center rounded-xl bg-accent-50 text-accent-700 dark:bg-accent-900/40 dark:text-accent-200"><x-ui.icon name="check-circle" size="5" /></span>
                    <span><span class="block text-[13px] font-semibold">{{ __('site.mock.new_order') }}</span><span class="block text-xs text-muted">{{ __('site.mock.new_order_sub') }}</span></span>
                </div>
                <div class="float-y absolute -start-2 bottom-16 rounded-2xl border border-line bg-surface p-3 shadow-pop sm:-start-8" style="animation-delay: -3s">
                    <x-ui.qr-pattern class="size-20 text-ink-950 dark:text-white" :cells="21" :seed="2" />
                    <p class="mt-1.5 text-center text-[10px] font-semibold uppercase tracking-wider text-muted">{{ __('site.mock.scan') }}</p>
                </div>
            </div>
            @endif
        </div>
    </section>

    {{-- Audience strip --}}
    <section class="border-y border-line bg-surface">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-x-10 gap-y-3 px-4 py-6 text-sm font-medium text-muted sm:px-6">
            <span class="eyebrow">{{ __('site.strip.title') }}</span>
            @foreach (['restaurants', 'cafes', 'bars', 'hotels', 'trucks'] as $kind)<span>{{ __('site.strip.'.$kind) }}</span>@endforeach
        </div>
    </section>

    {{-- Features: bento grid --}}
    @if (! empty($c['features']))
        <section id="features" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-20 sm:px-6 sm:py-28">
            <div class="mx-auto max-w-2xl text-center"><p class="eyebrow text-accent-700 dark:text-accent-300">{{ __('site.nav.features') }}</p><h2 class="display mt-3 text-3xl font-semibold sm:text-4xl">{{ __('site.features.title') }}</h2></div>
            <div class="mt-14 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                @foreach ($c['features'] as $i => $feature)
                    <div @class(['card group relative overflow-hidden p-6 transition hover:shadow-pop lg:col-span-3', 'lg:col-span-6' => $loop->last && $loop->count % 2 === 1, 'bg-ink-950 text-white !border-ink-950' => $i === 0])>
                        @if ($i === 0)<x-ui.qr-pattern class="pointer-events-none absolute -end-6 -top-6 size-44 text-white/[0.07]" :cells="21" :seed="31" />@endif
                        <span @class(['grid size-11 place-items-center rounded-xl', 'bg-brand-500 text-ink-950' => $i === 0, 'bg-brand-100 text-brand-800 dark:bg-brand-900/40 dark:text-brand-200' => $i !== 0])><x-ui.icon :name="$icons[$i % count($icons)]" size="6" /></span>
                        <h3 class="display relative mt-5 text-xl font-semibold">{{ $feature['title'] }}</h3>
                        <p @class(['relative mt-2 text-[15px] leading-relaxed', 'text-ink-300' => $i === 0, 'text-muted' => $i !== 0])>{{ $feature['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- How it works: a genuine sequence, so numbered --}}
    <section class="bg-surface-2/60 py-20 sm:py-28">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="mx-auto max-w-2xl text-center"><p class="eyebrow text-accent-700 dark:text-accent-300">{{ __('site.how.eyebrow') }}</p><h2 class="display mt-3 text-3xl font-semibold sm:text-4xl">{{ __('site.how.title') }}</h2></div>
            <ol class="mt-14 grid gap-6 md:grid-cols-3">
                @foreach (['menu' => 'store', 'print' => 'qr', 'orders' => 'zap'] as $step => $icon)
                    <li class="card relative p-6">
                        <span class="display absolute end-5 top-4 text-5xl font-semibold text-ink-200 dark:text-ink-800">{{ $loop->iteration }}</span>
                        <span class="grid size-11 place-items-center rounded-xl bg-ink-950 text-brand-400 dark:bg-white dark:text-ink-950"><x-ui.icon :name="$icon" size="6" /></span>
                        <h3 class="display mt-5 text-lg font-semibold">{{ __('site.how.'.$step.'_title') }}</h3>
                        <p class="mt-2 text-[15px] text-muted">{{ __('site.how.'.$step.'_text') }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Pricing, straight from the active plans --}}
    @if ($plans->isNotEmpty())
        <section id="pricing" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-20 sm:px-6 sm:py-28">
            <div class="mx-auto max-w-2xl text-center"><p class="eyebrow text-accent-700 dark:text-accent-300">{{ __('site.nav.pricing') }}</p><h2 class="display mt-3 text-3xl font-semibold sm:text-4xl">{{ $c['pricing']['title'] }}</h2><p class="mt-3 text-lg text-muted">{{ $c['pricing']['subtitle'] }}</p></div>
            <div class="mt-14 grid items-stretch gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($plans as $plan)
                    @php($dark = $plan->is_featured)
                    <div @class(['relative flex flex-col rounded-3xl p-7', 'bg-ink-950 text-white shadow-pop lg:-my-3 lg:py-10' => $dark, 'card' => ! $dark])>
                        @if ($dark)<span class="absolute -top-3 start-7 rounded-full bg-brand-500 px-3 py-1 text-xs font-bold text-ink-950">{{ __('site.pricing.popular') }}</span>@endif
                        <h3 class="display text-xl font-semibold">{{ $plan->name }}</h3>
                        <p @class(['mt-1 min-h-[2.5rem] text-sm', 'text-ink-300' => $dark, 'text-muted' => ! $dark])>{{ $plan->description }}</p>
                        <p class="mt-5 flex items-baseline gap-1.5">
                            @if ($plan->isFree())<span class="display tnum text-5xl font-semibold">{{ __('site.pricing.free') }}</span>
                            @else<span class="display tnum text-5xl font-semibold">{{ fmod((float) $plan->price, 1) == 0.0 ? number_format((float) $plan->price, 0) : number_format((float) $plan->price, 2) }}</span><span class="text-lg font-semibold">{{ $plan->currency_code }}</span>
                                <span @class(['text-sm', 'text-ink-400' => $dark, 'text-muted' => ! $dark])>{{ $plan->interval === 'lifetime' ? __('site.pricing.one_time') : '/ '.__('billing.interval_'.$plan->interval) }}</span>@endif
                        </p>
                        @if ($plan->trial_days > 0)<p class="mt-2 text-sm font-medium {{ $dark ? 'text-brand-400' : 'text-accent-700 dark:text-accent-300' }}">{{ __('site.pricing.trial', ['days' => $plan->trial_days]) }}</p>@endif
                        <ul class="mt-6 flex-1 space-y-3 border-t pt-6 text-sm {{ $dark ? 'border-white/10' : 'border-line' }}">
                            @foreach (\App\Modules\Billing\Models\Plan::LIMITS as $limit)
                                <li class="flex items-start gap-2.5"><x-ui.icon name="check" size="4" class="mt-0.5 {{ $dark ? 'text-brand-400' : 'text-accent-600' }}" /><span>{{ __('admin.plans.limit_'.$limit) }}: <strong class="tnum">{{ $plan->limit($limit) ?? __('site.pricing.unlimited') }}</strong></span></li>
                            @endforeach
                            @foreach (\App\Modules\Billing\Models\Plan::FEATURES as $feature)
                                @if ($plan->hasFeature($feature))<li class="flex items-start gap-2.5"><x-ui.icon name="check" size="4" class="mt-0.5 {{ $dark ? 'text-brand-400' : 'text-accent-600' }}" />{{ __('admin.plans.feature_'.$feature) }}</li>@endif
                            @endforeach
                        </ul>
                        <a href="{{ $registration ? route('register', ['plan' => $plan->slug]) : route('login') }}" @class(['btn btn-lg mt-8 w-full', 'btn-primary' => $dark, 'btn-secondary' => ! $dark])>{{ $plan->trial_days > 0 ? __('site.pricing.start_trial') : __('site.pricing.choose') }}</a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Testimonials --}}
    @if (! empty($c['testimonials']))
        <section class="bg-surface-2/60 py-20 sm:py-28">
            <div class="mx-auto grid max-w-6xl gap-6 px-4 sm:px-6 md:grid-cols-3">
                @foreach ($c['testimonials'] as $t)
                    <figure class="card flex flex-col p-6">
                        <x-ui.icon name="sparkles" size="5" class="text-brand-600" />
                        <blockquote class="mt-4 flex-1 text-[15px] leading-relaxed">“{{ $t['quote'] }}”</blockquote>
                        <figcaption class="mt-5 flex items-center gap-3"><x-ui.avatar :name="$t['name']" size="9" /><span class="text-sm"><strong class="block">{{ $t['name'] }}</strong>@if (! empty($t['role']))<span class="text-muted">{{ $t['role'] }}</span>@endif</span></figcaption>
                    </figure>
                @endforeach
            </div>
        </section>
    @endif

    {{-- FAQ --}}
    @if (! empty($c['faq']))
        <section id="faq" class="mx-auto max-w-3xl scroll-mt-20 px-4 py-20 sm:px-6 sm:py-28">
            <h2 class="display text-center text-3xl font-semibold sm:text-4xl">{{ __('site.nav.faq') }}</h2>
            <div class="mt-10 space-y-3">
                @foreach ($c['faq'] as $item)
                    <details class="card group px-5 py-4 open:shadow-pop">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold [&::-webkit-details-marker]:hidden">{{ $item['question'] }}<x-ui.icon name="chevron-down" size="5" class="text-muted transition group-open:rotate-180" /></summary>
                        <p class="mt-3 text-[15px] leading-relaxed text-muted">{{ $item['answer'] }}</p>
                    </details>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Closing call to action --}}
    <section class="px-4 pb-20 sm:px-6 sm:pb-28">
        <div class="relative mx-auto max-w-6xl overflow-hidden rounded-[2rem] bg-ink-950 px-6 py-16 text-center text-white sm:px-12">
            <x-ui.qr-pattern class="pointer-events-none absolute -start-10 -top-10 size-64 text-white/[0.06]" :cells="21" :seed="41" />
            <x-ui.qr-pattern class="pointer-events-none absolute -bottom-10 -end-10 size-64 text-white/[0.06]" :cells="21" :seed="43" />
            <div class="pointer-events-none absolute left-1/2 top-0 size-80 -translate-x-1/2 rounded-full bg-brand-500/25 blur-3xl"></div>
            <h2 class="display relative mx-auto max-w-2xl text-3xl font-semibold sm:text-5xl">{{ __('site.cta.title') }}</h2>
            <p class="relative mx-auto mt-4 max-w-xl text-lg text-ink-300">{{ __('site.cta.text') }}</p>
            <a href="{{ $primaryUrl }}" class="btn btn-primary btn-lg relative mt-8">{{ $c['hero']['cta_label'] }}<x-ui.icon name="arrow-right" size="5" class="rtl:rotate-180" /></a>
        </div>
    </section>

    {{-- Contact --}}
    @php($contact = $c['contact'])
    @if (! empty($contact['email']) || ! empty($contact['phone']) || ! empty($contact['address']))
        <section id="contact" class="mx-auto max-w-3xl scroll-mt-20 px-4 pb-20 text-center sm:px-6">
            <h2 class="display text-2xl font-semibold">{{ $contact['title'] }}</h2>
            <p class="mt-2 text-muted">{{ $contact['text'] }}</p>
            <p class="mt-5 flex flex-wrap justify-center gap-x-6 gap-y-2 font-medium">
                @if (! empty($contact['email']))<a class="link inline-flex items-center gap-2" href="mailto:{{ $contact['email'] }}"><x-ui.icon name="mail" size="4" />{{ $contact['email'] }}</a>@endif
                @if (! empty($contact['phone']))<a class="link" href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a>@endif
            </p>
            @if (! empty($contact['address']))<p class="mt-2 text-sm text-muted">{{ $contact['address'] }}</p>@endif
        </section>
    @endif
</x-layouts.site>
