<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="dark" style="font-size:112.5%">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ __('orders.kds_title') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-ink-950 text-ink-50 antialiased" x-data="kdsBoard(@js($config))" x-on:keydown.escape.window="">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('orders.board') }}" class="rounded-lg px-3 py-1.5 text-sm text-ink-300 hover:bg-white/10">← {{ __('orders.board_title') }}</a>
            <template x-if="stations.length">
                <nav class="flex flex-wrap gap-1.5" aria-label="{{ __('orders.station') }}">
                    <button type="button" class="rounded-full px-4 py-1.5 text-sm font-semibold" :class="station === '' ? 'bg-brand-500 text-ink-950' : 'bg-white/10'" x-on:click="station = ''">{{ __('orders.kds_all') }}</button>
                    <template x-for="s in stations" :key="s"><button type="button" class="rounded-full px-4 py-1.5 text-sm font-semibold" :class="station === s ? 'bg-brand-500 text-ink-950' : 'bg-white/10'" x-on:click="station = s" x-text="s"></button></template>
                </nav>
            </template>
        </div>
        <div class="flex items-center gap-2">
            <span class="rounded-full bg-red-600 px-3 py-1 text-sm font-semibold" x-show="offline" x-cloak>{{ __('orders.offline') }}</span>
            <button type="button" class="rounded-lg bg-white/10 px-3 py-1.5 text-sm" x-on:click="toggleSound()" :aria-pressed="sound" x-text="sound ? @js(__('orders.sound_on')) : @js(__('orders.sound_off'))"></button>
            <button type="button" class="rounded-lg bg-white/10 px-3 py-1.5 text-sm" x-on:click="document.documentElement.requestFullscreen && document.documentElement.requestFullscreen()">{{ __('orders.kds_fullscreen') }}</button>
        </div>
    </header>

    <main class="grid gap-4 p-4 lg:grid-cols-3">
        @foreach ([['toStart', 'kds_col_new', 'bg-brand-500'], ['cooking', 'kds_col_cooking', 'bg-blue-500'], ['ready', 'kds_col_ready', 'bg-emerald-500']] as [$list, $label, $dot])
            <section aria-label="{{ __('orders.'.$label) }}">
                <h2 class="mb-3 flex items-center justify-between text-lg font-bold"><span class="flex items-center gap-2"><span class="size-3 rounded-full {{ $dot }}"></span>{{ __('orders.'.$label) }}</span><span class="tnum rounded-full bg-white/10 px-2.5 py-0.5 text-sm" x-text="{{ $list }}.length"></span></h2>
                <div class="space-y-3">
                    <template x-for="o in {{ $list }}" :key="o.id">
                        <article class="overflow-hidden rounded-2xl border-2 bg-ink-900" :class="[color(o), fresh.has(o.id) ? 'animate-pulse' : '']">
                            <div class="flex items-center justify-between gap-3 px-4 py-3" :class="bar(o)">
                                <p class="display text-3xl font-extrabold leading-none" x-text="'#' + o.number"></p>
                                <p class="text-right text-sm font-semibold"><span x-text="where(o)"></span><span class="tnum block text-xl font-extrabold" x-text="age(o) + ' min'"></span></p>
                            </div>
                            <ul class="space-y-2 px-4 py-3 text-lg">
                                <template x-for="i in lines(o)" :key="i.id">
                                    <li :class="i.done ? 'opacity-40 line-through' : ''"><span class="tnum font-extrabold" x-text="i.qty + '×'"></span> <span class="font-semibold" x-text="i.name"></span>
                                        <span class="block text-sm text-ink-300" x-show="i.options" x-text="i.options"></span>
                                        <span class="block text-sm font-semibold text-brand-300" x-show="i.note" x-text="'“' + i.note + '”'"></span></li>
                                </template>
                            </ul>
                            <p class="mx-4 mb-3 rounded-lg bg-brand-500/20 px-3 py-2 text-sm font-semibold text-brand-200" x-show="o.note" x-text="o.note"></p>
                            <div class="flex gap-2 px-4 pb-4" x-show="o.status !== 'ready'">
                                <button type="button" class="flex-1 rounded-xl bg-white px-4 py-3 text-lg font-extrabold text-ink-950 active:scale-95 disabled:opacity-40" :disabled="busy === o.id || (o.status === 'new' && !canStart)" x-on:click="advance(o)" x-text="actionLabel(o)"></button>
                            </div>
                        </article>
                    </template>
                    <p class="rounded-2xl border border-dashed border-white/20 px-4 py-10 text-center text-ink-400" x-show="{{ $list }}.length === 0">{{ __('orders.kds_empty') }}</p>
                </div>
            </section>
        @endforeach
    </main>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('kdsBoard', (cfg) => ({
                orders: cfg.orders, stations: cfg.stations, station: '', offline: false, busy: null, fresh: new Set(), seen: new Set(cfg.orders.map((o) => o.id)),
                skew: Date.now() - Date.parse(cfg.now), tick: Date.now(), ctx: null, canStart: true,
                sound: (() => { try { return localStorage.getItem('orders.sound') !== 'off'; } catch (e) { return true; } })(),
                init() {
                    setInterval(() => { this.tick = Date.now(); }, 15000);
                    this.poll();
                    if ('wakeLock' in navigator) { const lock = () => navigator.wakeLock.request('screen').catch(() => {}); lock(); document.addEventListener('visibilitychange', () => { if (!document.hidden) { lock(); } }); }
                },
                async poll() {
                    try {
                        const res = await fetch(cfg.feedUrl, { headers: { Accept: 'application/json' } });
                        if (!res.ok) { throw new Error(); }
                        const data = await res.json();
                        this.offline = false; this.skew = Date.now() - Date.parse(data.now);
                        const arrived = data.orders.filter((o) => !this.seen.has(o.id) && o.status === 'new');
                        data.orders.forEach((o) => this.seen.add(o.id));
                        this.orders = data.orders;
                        if (arrived.length) { arrived.forEach((o) => this.fresh.add(o.id)); setTimeout(() => arrived.forEach((o) => this.fresh.delete(o.id)), 10000); this.beep(); }
                    } catch (e) { this.offline = true; }
                    setTimeout(() => this.poll(), document.hidden ? cfg.interval * 4 : cfg.interval);
                },
                relevant(o) { return !this.station || o.items.some((i) => i.station === this.station); },
                lines(o) { return this.station ? o.items.filter((i) => i.station === this.station) : o.items; },
                get toStart() { return this.orders.filter((o) => ['new', 'accepted'].includes(o.status) && !o.scheduled_far && this.relevant(o)); },
                get cooking() { return this.orders.filter((o) => o.status === 'preparing' && this.relevant(o) && (!this.station || this.lines(o).some((i) => !i.done))); },
                get ready() { return this.orders.filter((o) => o.status === 'ready' && this.relevant(o)).slice(-6); },
                age(o) { return Math.max(0, Math.floor((this.tick - this.skew - Date.parse(o.created)) / 60000)); },
                where(o) { return o.type === 'dine_in' ? (o.table || '?') : (o.name || o.type); },
                color(o) { const a = this.age(o), p = o.prep_minutes || 15; return a > p + 5 ? 'border-red-500' : (a > p * 0.7 ? 'border-amber-400' : 'border-emerald-500/60'); },
                bar(o) { const a = this.age(o), p = o.prep_minutes || 15; return a > p + 5 ? 'bg-red-600' : (a > p * 0.7 ? 'bg-amber-500 text-ink-950' : 'bg-white/5'); },
                actionLabel(o) {
                    if (o.status !== 'preparing') { return @js(__('orders.kds_start')); }
                    return this.station ? @js(__('orders.kds_station_done', ['station' => ':s'])).replace(':s', this.station) : @js(__('orders.kds_ready'));
                },
                async advance(o) {
                    if (this.busy) { return; }
                    this.busy = o.id;
                    const headers = { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf };
                    try {
                        if (o.status !== 'preparing') { await fetch(cfg.statusUrl + '/' + o.id + '/status', { method: 'POST', headers, body: JSON.stringify({ status: 'preparing' }) }); }
                        else { await fetch(cfg.statusUrl + '/' + o.id + '/station-done', { method: 'POST', headers, body: JSON.stringify({ station: this.station }) }); }
                    } catch (e) { /* the next poll shows the truth */ }
                    this.busy = null; this.poll();
                },
                toggleSound() { this.sound = !this.sound; try { localStorage.setItem('orders.sound', this.sound ? 'on' : 'off'); } catch (e) {} if (this.sound) { this.beep(); } },
                beep() {
                    if (!this.sound) { return; }
                    try {
                        this.ctx ??= new (window.AudioContext || window.webkitAudioContext)();
                        [0, 0.25, 0.5].forEach((d, i) => { const o = this.ctx.createOscillator(), g = this.ctx.createGain(); o.type = 'square'; o.frequency.value = i === 1 ? 880 : 660; g.gain.value = 0.12; o.connect(g).connect(this.ctx.destination); o.start(this.ctx.currentTime + d); o.stop(this.ctx.currentTime + d + 0.18); });
                    } catch (e) { /* blocked until the first tap */ }
                },
            }));
        });
    </script>
    @livewireScripts
</body>
</html>
