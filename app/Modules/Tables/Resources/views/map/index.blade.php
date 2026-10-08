<x-layouts.app :title="__('tables.map_title')">
    <x-ui.page-header :title="__('tables.map_title')" :description="__('tables.map_sub')">
        <x-slot:actions><a href="{{ route('tables.index') }}" class="btn btn-secondary">{{ __('tables.title') }}</a></x-slot:actions>
    </x-ui.page-header>

    <div x-data="floorMap(@js($config))" class="space-y-4">
        @if ($areas->isNotEmpty())
            <nav class="flex flex-wrap gap-2" aria-label="{{ __('tables.areas') }}">
                <a href="{{ route('tables.map') }}" class="btn btn-sm {{ $areaId ? 'btn-secondary' : 'btn-primary' }}">{{ __('tables.all_areas') }}</a>
                @foreach ($areas as $area)<a href="{{ route('tables.map', ['area' => $area->id]) }}" class="btn btn-sm {{ $areaId === $area->id ? 'btn-primary' : 'btn-secondary' }}">{{ $area->name }}</a>@endforeach
            </nav>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <ul class="flex flex-wrap gap-3 text-xs" aria-label="{{ __('tables.map_legend') }}">
                @foreach (['free' => 'bg-emerald-500', 'busy' => 'bg-amber-500', 'ready' => 'bg-sky-500', 'unpaid' => 'bg-rose-500'] as $state => $color)
                    <li class="inline-flex items-center gap-1.5"><span class="size-3 rounded-full {{ $color }}"></span>{{ __('tables.state_'.$state) }}</li>
                @endforeach
            </ul>
            @if ($canArrange)
                <div class="flex items-center gap-2">
                    <span class="text-sm text-muted" x-show="arranging" x-cloak>{{ __('tables.map_drag_help') }}</span>
                    <button type="button" class="btn btn-secondary btn-sm" x-show="arranging" x-cloak x-on:click="cycleShape()" :disabled="!selected">{{ __('tables.map_shape') }}</button>
                    <button type="button" class="btn btn-sm" :class="arranging ? 'btn-primary' : 'btn-secondary'" x-on:click="arranging ? save() : (arranging = true)"><span x-text="arranging ? @js(__('admin.save')) : @js(__('tables.map_arrange'))"></span></button>
                </div>
            @endif
        </div>

        <p class="text-sm font-medium" x-show="message" x-text="message" role="status" x-cloak></p>

        {{-- The canvas is 1000 x 700 "map units"; positions are percentages of it, so it scales to any screen. --}}
        <div x-ref="canvas" class="relative w-full overflow-hidden rounded-2xl border border-line bg-surface-2" style="aspect-ratio: 10 / 7; touch-action: none"
             x-on:pointermove.window="drag($event)" x-on:pointerup.window="drop()" x-on:pointercancel.window="drop()">
            <template x-for="t in tables" :key="t.id">
                <component :is="'div'">
                    <button type="button" x-on:pointerdown="grab($event, t)" x-on:click="tap(t)"
                            class="absolute flex -translate-x-1/2 -translate-y-1/2 flex-col items-center justify-center border-2 text-center shadow-sm transition-colors focus-visible:ring-2 focus-visible:ring-accent-500"
                            :class="[shapeClass(t), stateClass(t), arranging ? 'cursor-grab' : '', selected === t.id && arranging ? 'ring-2 ring-accent-600' : '']"
                            :style="`left:${t.x / 10}%;top:${t.y / 10}%`" :aria-label="t.name + ', ' + stateLabel(t)">
                        <span class="text-sm font-bold leading-tight" x-text="t.name"></span>
                        <span class="text-[11px] opacity-80" x-show="t.seats" x-text="t.seats + ' ' + @js(__('tables.seats_short'))"></span>
                        <span class="text-[11px] font-semibold" x-show="!arranging && stateOf(t) !== 'free'" x-text="elapsed(t)"></span>
                    </button>
                </component>
            </template>
            <p class="absolute inset-0 grid place-items-center text-sm text-muted" x-show="!tables.length" x-cloak>{{ __('tables.empty_title') }}</p>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('floorMap', (cfg) => ({
                tables: cfg.tables, states: {}, arranging: false, selected: null, grabbed: null, message: '', timer: null,
                init() { this.poll(); this.timer = setInterval(() => { if (!this.arranging && !document.hidden) { this.poll(); } }, 6000); },
                async poll() {
                    try { const r = await fetch(cfg.statusUrl, { headers: { Accept: 'application/json' } }); if (r.ok) { this.states = (await r.json()).states || {}; } } catch (e) { /* offline: keep last state */ }
                },
                stateOf(t) { return this.states[t.id]?.state || 'free'; },
                stateLabel(t) { return ({ free: @js(__('tables.state_free')), busy: @js(__('tables.state_busy')), ready: @js(__('tables.state_ready')), unpaid: @js(__('tables.state_unpaid')) })[this.stateOf(t)]; },
                stateClass(t) {
                    return ({ free: 'border-emerald-500 bg-emerald-50 text-emerald-900 dark:bg-emerald-900/30 dark:text-emerald-100', busy: 'border-amber-500 bg-amber-50 text-amber-900 dark:bg-amber-900/30 dark:text-amber-100',
                        ready: 'border-sky-500 bg-sky-50 text-sky-900 dark:bg-sky-900/30 dark:text-sky-100', unpaid: 'border-rose-500 bg-rose-50 text-rose-900 dark:bg-rose-900/30 dark:text-rose-100' })[this.stateOf(t)];
                },
                shapeClass(t) { return ({ square: 'h-[5.5rem] w-[5.5rem] rounded-xl', round: 'size-[5.5rem] rounded-full', wide: 'h-[5.5rem] w-36 rounded-xl' })[t.shape] || 'size-[5.5rem] rounded-xl'; },
                elapsed(t) {
                    const s = this.states[t.id]; if (!s?.since) { return ''; }
                    const m = Math.max(0, Math.round((Date.now() - new Date(s.since).getTime()) / 60000));
                    return m < 60 ? m + ' min' : Math.floor(m / 60) + ' h ' + (m % 60) + ' min';
                },
                tap(t) {
                    if (this.arranging) { this.selected = t.id; return; }
                    if (cfg.posUrl) { window.location.href = cfg.posUrl + '?table=' + t.id; } else if (cfg.orderUrl) { window.location.href = cfg.orderUrl; }
                },
                grab(e, t) { if (!this.arranging) { return; } this.grabbed = t; this.selected = t.id; e.currentTarget.setPointerCapture?.(e.pointerId); },
                drag(e) {
                    if (!this.grabbed) { return; }
                    const r = this.$refs.canvas.getBoundingClientRect();
                    this.grabbed.x = Math.round(Math.min(1000, Math.max(0, ((e.clientX - r.left) / r.width) * 1000)));
                    this.grabbed.y = Math.round(Math.min(1000, Math.max(0, ((e.clientY - r.top) / r.height) * 1000)));
                },
                drop() { this.grabbed = null; },
                cycleShape() { const t = this.tables.find((x) => x.id === this.selected); if (t) { t.shape = ({ square: 'round', round: 'wide', wide: 'square' })[t.shape]; } },
                async save() {
                    const res = await fetch(cfg.saveUrl, { method: 'PUT', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                        body: JSON.stringify({ tables: this.tables.map((t) => ({ id: t.id, x: t.x, y: t.y, shape: t.shape })) }) });
                    this.message = res.ok ? @js(__('tables.map_saved')) : @js(__('tables.map_save_failed'));
                    if (res.ok) { this.arranging = false; this.selected = null; }
                },
            }));
        });
    </script>
</x-layouts.app>
