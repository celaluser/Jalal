@php
    $t = collect(['new', 'accepted', 'preparing', 'ready'])->mapWithKeys(fn ($k) => ['step_'.$k => __('orders.step_'.$k)])->all()
        + ['step_completed' => __('orders.step_completed_'.$order->type), 'msg_new' => __('orders.msg_new'), 'msg_accepted' => __('orders.msg_accepted'), 'msg_preparing' => __('orders.msg_preparing'),
           'msg_ready' => __('orders.msg_ready_'.$order->type), 'msg_completed' => __('orders.msg_completed'), 'msg_cancelled' => __('orders.msg_cancelled'), 'estimated' => __('orders.estimated'), 'cancel_confirm' => __('orders.cancel_mine_confirm'), 'msg_on_the_way' => __('orders.msg_on_the_way'), 'push_on' => __('orders.push_on'), 'push_denied' => __('orders.push_denied')];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="{{ $restaurant->brandColor() }}">
    <title>{{ __('orders.track_title', ['number' => $order->label()]) }} · {{ $restaurant->name }}</title>
    <style>{!! $themeCss !!}</style>
    @vite(['resources/css/app.css'])
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('orderStatus', (cfg) => ({
                s: cfg.state, now: Date.now(), timer: null, busy: false,
                init() { setInterval(() => { this.now = Date.now(); }, 15000); this.poll(); },
                get stepIndex() { return this.s.status === 'cancelled' ? -1 : this.s.steps.indexOf(this.s.status); },
                get message() { return this.s.dispatched && this.s.status === 'ready' ? cfg.t.msg_on_the_way : cfg.t['msg_' + this.s.status]; },
                pushMsg: '',
                async enablePush() {
                    try {
                        if (!('serviceWorker' in navigator) || !('PushManager' in window)) { this.pushMsg = cfg.t.push_denied; return; }
                        if ((await Notification.requestPermission()) !== 'granted') { this.pushMsg = cfg.t.push_denied; return; }
                        const reg = await navigator.serviceWorker.register(cfg.workerUrl).then(() => navigator.serviceWorker.ready);
                        const raw = atob(this.s.push_key.replace(/-/g, '+').replace(/_/g, '/'));
                        const key = Uint8Array.from(raw, (c) => c.charCodeAt(0));
                        const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
                        const res = await fetch(cfg.pushUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf }, body: JSON.stringify(sub.toJSON()) });
                        if (res.ok) { this.s.push_key = null; this.pushMsg = cfg.t.push_on; } else { this.pushMsg = cfg.t.push_denied; }
                    } catch (e) { this.pushMsg = cfg.t.push_denied; }
                },
                get minutesLeft() { return this.s.eta ? Math.max(0, Math.ceil((Date.parse(this.s.eta) - this.now) / 60000)) : null; },
                stepLabel(step) { return cfg.t['step_' + step]; },
                poll() {
                    clearTimeout(this.timer);
                    if (!this.s.open) { return; }
                    this.timer = setTimeout(async () => {
                        try { const r = await fetch(cfg.statusUrl, { headers: { Accept: 'application/json' } }); if (r.ok) { this.s = await r.json(); } } catch (e) { /* retry */ }
                        this.poll();
                    }, document.hidden ? 15000 : 4000);
                },
                async cancel() {
                    if (this.busy || !confirm(cfg.t.cancel_confirm)) { return; }
                    this.busy = true;
                    try { const r = await fetch(cfg.cancelUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf } }); if (r.ok) { this.s = await r.json(); } } catch (e) { /* ignore */ }
                    this.busy = false;
                },
            }));
        });
    </script>
    @livewireStyles
</head>
<body class="menu-page min-h-screen pb-10" x-data="orderStatus(@js(['state' => $state, 'statusUrl' => $statusUrl, 'cancelUrl' => $cancelUrl, 'csrf' => csrf_token(), 't' => $t, 'pushUrl' => $pushUrl, 'workerUrl' => $workerUrl]))">
    <header class="menu-hero">
        <div class="mx-auto max-w-xl px-4 pb-16 pt-6 text-center">
            <p class="text-sm font-semibold opacity-90">{{ $restaurant->name }}</p>
            <p class="mt-4 text-sm font-medium opacity-90">{{ __('orders.your_number') }}</p>
            <p class="display tnum menu-rise text-6xl font-bold leading-none">{{ $order->label() }}</p>
            <p class="mt-3 text-sm opacity-90">{{ __('orders.track_sub') }}</p>
        </div>
    </header>

    <main class="mx-auto -mt-10 max-w-xl space-y-4 px-4">
        {{-- Progress --}}
        <section class="menu-card p-5" aria-live="polite">
            <p class="display text-xl font-bold" x-text="message"></p>
            <p class="menu-muted mt-1 text-sm" x-show="s.open && minutesLeft !== null && ['accepted','preparing'].includes(s.status)" x-text="cfg_est(minutesLeft)" x-data="{ cfg_est: (m) => @js(__('orders.estimated')).replace(':count', m) }"></p>

            <ol class="mt-5 space-y-0" x-show="s.status !== 'cancelled'">
                <template x-for="(step, i) in s.steps" :key="step">
                    <li class="relative flex gap-3 pb-5 last:pb-0">
                        <span class="absolute start-[0.9rem] top-8 h-[calc(100%-1.5rem)] w-0.5" :style="i < stepIndex ? 'background: var(--menu-accent)' : 'background: var(--menu-line)'" x-show="i < s.steps.length - 1"></span>
                        <span class="relative grid size-7 shrink-0 place-items-center rounded-full text-xs font-bold transition" :class="i === stepIndex && s.open ? 'menu-pop' : ''"
                              :style="i <= stepIndex ? 'background: var(--menu-accent); color: var(--menu-accent-fg)' : 'background: var(--menu-line)'">
                            <svg x-show="i < stepIndex || (i === stepIndex && !s.open)" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m4 10 4 4 8-8"/></svg>
                            <span x-show="!(i < stepIndex || (i === stepIndex && !s.open))" x-text="i + 1"></span>
                        </span>
                        <span class="pt-0.5" :class="i === stepIndex ? 'font-bold' : (i < stepIndex ? '' : 'menu-muted')" x-text="stepLabel(step)"></span>
                    </li>
                </template>
            </ol>

            <p class="menu-muted mt-3 text-sm" x-show="s.scheduled" x-text="@js(__('orders.scheduled_for', ['time' => ':t'])).replace(':t', s.scheduled)"></p>
            <p class="menu-muted mt-1 text-sm" x-show="s.vehicle" x-text="@js(__('orders.vehicle')) + ': ' + s.vehicle"></p>
            <p class="menu-muted mt-1 text-sm" x-show="s.room" x-text="@js(__('orders.room')) + ' ' + s.room"></p>
            <button type="button" class="menu-btn menu-btn-quiet mt-4 w-full" x-show="s.push_key && s.open" x-cloak x-on:click="enablePush()">🔔 {{ __('orders.enable_push') }}</button>
            <p class="mt-2 text-sm font-medium" x-show="pushMsg" x-text="pushMsg" role="status"></p>

            @if ($canCancel)
                <button type="button" class="menu-btn menu-btn-quiet mt-5 w-full" x-show="s.can_cancel" x-on:click="cancel()" :disabled="busy">{{ __('orders.cancel_mine') }}</button>
            @else
                <template x-if="s.can_cancel"><button type="button" class="menu-btn menu-btn-quiet mt-5 w-full" x-on:click="cancel()" :disabled="busy">{{ __('orders.cancel_mine') }}</button></template>
            @endif
        </section>

        {{-- The order --}}
        <section class="menu-card p-5">
            <h2 class="mb-3 font-bold">{{ __('orders.your_items') }}</h2>
            <ul class="menu-divide">
                <template x-for="(i, n) in s.items" :key="n">
                    <li class="flex justify-between gap-3 py-2.5 text-sm">
                        <span><span class="tnum font-bold" x-text="i.qty + '×'"></span> <span x-text="i.name"></span><span class="menu-muted block text-xs" x-show="i.options" x-text="i.options"></span></span>
                        <span class="tnum shrink-0 font-medium" x-text="i.total"></span>
                    </li>
                </template>
            </ul>
            <p class="menu-muted mt-3 flex justify-between text-sm" x-show="s.packaging" x-cloak><span>{{ __('orders.packaging') }}</span><span class="tnum" x-text="s.packaging"></span></p>
            <p class="menu-accent-text mt-3 flex justify-between text-sm font-semibold" x-show="s.discount" x-cloak><span x-text="@js(__('marketing.promo_discount')) + ' · ' + (s.discount ? s.discount.code : '')"></span><span class="tnum" x-text="s.discount ? '−' + s.discount.amount : ''"></span></p>
            <div class="menu-line mt-3 flex items-baseline justify-between border-t pt-3 text-lg font-bold"><span>{{ __('orders.total') }}</span><span class="tnum" x-text="s.total"></span></div>
            <p class="mt-2 text-sm"><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="s.paid ? 'menu-accent' : 'menu-surface border menu-line'" x-text="s.paid ? @js(__('orders.paid')) : @js(__('orders.unpaid'))"></span></p>
        </section>

        {{-- Loyalty reward earned by this order --}}
        <section class="menu-card p-5 text-center" x-show="s.reward" x-cloak>
            <p class="display text-xl font-bold">{{ __('marketing.reward_earned') }}</p>
            <p class="menu-muted mt-1 text-sm">{{ __('marketing.reward_earned_text') }}</p>
            <p class="menu-accent mx-auto mt-4 inline-block rounded-xl px-5 py-3 font-mono text-xl font-bold tracking-widest" dir="ltr" x-text="s.reward ? s.reward.code : ''"></p>
            <p class="mt-2 text-sm font-semibold menu-accent-text" x-text="s.reward ? s.reward.text : ''"></p>
            <p class="menu-muted mt-1 text-xs" x-text="s.reward ? @js(__('marketing.reward_valid_until', ['date' => ':date'])).replace(':date', s.reward.until) : ''"></p>
        </section>

        {{-- Feedback --}}
        @if (session('review_thanks'))<p class="menu-card menu-accent-text p-4 text-center font-semibold" role="status">{{ __('marketing.rate_thanks') }}</p>@endif
        <section class="menu-card p-5" x-show="s.review && (s.review.open || s.review.rating)" x-cloak x-data="{ rating: 0 }">
            <template x-if="s.review && s.review.open">
                <form method="POST" action="{{ $reviewUrl }}" class="space-y-3">
                    @csrf
                    <div><h2 class="display text-xl font-bold">{{ __('marketing.rate_title') }}</h2><p class="menu-muted text-sm">{{ __('marketing.rate_sub') }}</p></div>
                    @if ($errors->has('review'))<p class="text-sm font-medium text-red-600" role="alert">{{ $errors->first('review') }}</p>@endif
                    <div class="flex justify-center gap-1" role="radiogroup" aria-label="{{ __('marketing.rate_title') }}">
                        @for ($i = 1; $i <= 5; $i++)
                            <button type="button" role="radio" :aria-checked="rating === {{ $i }}" aria-label="{{ trans_choice('marketing.stars', $i, ['count' => $i]) }}" class="menu-accent-text p-1 transition active:scale-90" x-on:click="rating = {{ $i }}">
                                <svg class="size-9" :class="rating >= {{ $i }} ? 'fill-current' : 'fill-none opacity-40'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" aria-hidden="true"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
                            </button>
                        @endfor
                    </div>
                    <input type="hidden" name="rating" :value="rating">
                    <textarea name="comment" rows="3" maxlength="1000" class="menu-card w-full px-3 py-2.5" placeholder="{{ __('marketing.rate_comment') }}" aria-label="{{ __('marketing.rate_comment') }}"></textarea>
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="is_public" value="1" checked class="mt-0.5 size-4 shrink-0 accent-[var(--menu-accent)]"><span>{{ __('marketing.rate_public') }}</span></label>
                    <button class="menu-btn w-full !py-3" :disabled="!rating">{{ __('marketing.rate_send') }}</button>
                </form>
            </template>
            <template x-if="s.review && !s.review.open && s.review.rating">
                <div>
                    <p class="font-bold">{{ __('marketing.rate_yours') }}</p>
                    <p class="menu-accent-text mt-1 text-2xl tracking-wider" aria-hidden="true" x-text="'★'.repeat(s.review.rating) + '☆'.repeat(5 - s.review.rating)"></p>
                    <div class="menu-line mt-3 border-t pt-3 text-sm" x-show="s.review.reply"><p class="menu-muted text-xs font-semibold uppercase">{{ __('marketing.rate_reply') }}</p><p x-text="s.review.reply"></p></div>
                </div>
            </template>
        </section>

        <a href="{{ $reorderUrl }}" class="menu-btn menu-btn-quiet w-full !py-3.5" x-show="!s.open" x-cloak>{{ __('orders.order_again') }}</a>
        <a href="{{ $menuUrl }}" class="menu-btn w-full !py-3.5">{{ __('orders.order_more') }}</a>
    </main>
    @livewireScripts
</body>
</html>
