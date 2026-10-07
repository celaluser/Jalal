@php
    $t = collect(['new', 'accepted', 'preparing', 'ready'])->mapWithKeys(fn ($k) => ['step_'.$k => __('orders.step_'.$k)])->all()
        + ['step_completed' => __('orders.step_completed_'.$order->type), 'msg_new' => __('orders.msg_new'), 'msg_accepted' => __('orders.msg_accepted'), 'msg_preparing' => __('orders.msg_preparing'),
           'msg_ready' => __('orders.msg_ready_'.$order->type), 'msg_completed' => __('orders.msg_completed'), 'msg_cancelled' => __('orders.msg_cancelled'), 'estimated' => __('orders.estimated'), 'cancel_confirm' => __('orders.cancel_mine_confirm')];
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
                get message() { return cfg.t['msg_' + this.s.status]; },
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
<body class="menu-page min-h-screen pb-10" x-data="orderStatus(@js(['state' => $state, 'statusUrl' => $statusUrl, 'cancelUrl' => $cancelUrl, 'csrf' => csrf_token(), 't' => $t]))">
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
            <div class="menu-line mt-3 flex items-baseline justify-between border-t pt-3 text-lg font-bold"><span>{{ __('orders.total') }}</span><span class="tnum" x-text="s.total"></span></div>
            <p class="mt-2 text-sm"><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="s.paid ? 'menu-accent' : 'menu-surface border menu-line'" x-text="s.paid ? @js(__('orders.paid')) : @js(__('orders.unpaid'))"></span></p>
        </section>

        <a href="{{ $menuUrl }}" class="menu-btn w-full !py-3.5" x-show="!s.open || true">{{ __('orders.order_more') }}</a>
    </main>
    @livewireScripts
</body>
</html>
