<x-layouts.app :title="__('orders.zones_title')">
    <x-ui.page-header :title="__('orders.zones_title')" :description="__('orders.zones_sub')" />
    @php($restaurant = auth()->user()->restaurant)
    @push('head')@vite(['resources/js/map.js'])@endpush
    <div class="max-w-3xl space-y-5">
        @if ($zones->isNotEmpty())
            @php($mapZones = $zones->map(fn ($z) => ['id' => $z->id, 'name' => $z->name, 'polygon' => $z->polygon])->values())
            <x-ui.card :title="__('orders.map_title')">
                <p class="-mt-1 mb-3 text-sm text-muted">{{ __('orders.map_help') }}</p>
                <div x-data="zoneMap(@js(['zones' => $mapZones, 'restaurant' => $restaurant->lat !== null ? ['lat' => (float) $restaurant->lat, 'lng' => (float) $restaurant->lng] : null, 'tile' => config('maps.tile_url'), 'attribution' => config('maps.attribution'), 'maxZoom' => config('maps.max_zoom'), 'labels' => ['restaurant' => __('orders.map_restaurant')], 'areaUrl' => url('delivery/zones'), 'noMap' => __('orders.map_unavailable')]))" x-init="start()">
                    <div class="mb-3 flex flex-wrap items-end gap-3">
                        <div class="min-w-[12rem] flex-1"><label class="mb-1 block text-sm font-medium" for="map-zone">{{ __('orders.map_zone') }}</label>
                            <select id="map-zone" x-model.number="current" x-on:change="pick()" class="field w-full">
                                <template x-for="z in zones" :key="z.id"><option :value="z.id" x-text="z.name + (z.polygon ? '' : ' · ' + @js(__('orders.map_no_area')))"></option></template>
                            </select></div>
                        <button type="button" class="btn btn-secondary btn-sm" x-on:click="editor && editor.undo()">{{ __('orders.map_undo') }}</button>
                        <button type="button" class="btn btn-secondary btn-sm" x-on:click="editor && editor.clear()">{{ __('orders.map_clear') }}</button>
                    </div>
                    <div x-ref="map" class="h-80 w-full overflow-hidden rounded-xl border border-line" role="application" aria-label="{{ __('orders.map_title') }}"></div>
                    <p class="mt-1 text-xs text-muted">{{ __('orders.map_privacy') }}</p>
                    @error('polygon')<p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <form method="POST" :action="areaUrl + '/' + current + '/area'" class="inline">@csrf @method('PUT')<input type="hidden" name="polygon" :value="polygon"><button class="btn btn-primary btn-sm" :disabled="!polygon">{{ __('orders.map_save') }}</button></form>
                        <form method="POST" :action="areaUrl + '/' + current + '/area'" class="inline">@csrf @method('PUT')<input type="hidden" name="clear" value="1"><button class="btn btn-secondary btn-sm">{{ __('orders.map_remove') }}</button></form>
                        <span class="mx-1 hidden h-5 w-px bg-line sm:block"></span>
                        <button type="button" class="btn btn-secondary btn-sm" x-on:click="locate()">📍 {{ __('orders.map_locate') }}</button>
                        <form method="POST" action="{{ route('delivery.location') }}" class="inline" x-show="home" x-cloak>@csrf @method('PUT')<input type="hidden" name="lat" :value="home && home[0]"><input type="hidden" name="lng" :value="home && home[1]"><button class="btn btn-secondary btn-sm">{{ __('orders.map_save_location') }}</button></form>
                    </div>
                </div>
            </x-ui.card>
            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('zoneMap', (cfg) => ({
                        zones: cfg.zones, current: cfg.zones[0].id, polygon: '', editor: null, home: null, areaUrl: cfg.areaUrl,
                        start() {
                            const boot = () => {
                                if (!window.qrmMaps) { return setTimeout(boot, 100); }
                                this.editor = window.qrmMaps.zoneEditor(this.$refs.map, { ...cfg, others: [], onChange: (json) => { this.polygon = json; }, onHome: (p) => { this.home = p; } });
                                this.pick();
                            };
                            boot();
                        },
                        pick() {
                            const z = this.zones.find((x) => x.id === this.current);
                            this.editor.load(z && z.polygon ? z.polygon : []);
                        },
                        locate() { this.editor.locate(); },
                    }));
                });
            </script>
        @endif

        <x-ui.card :title="__('orders.zone_add')">
            <form method="POST" action="{{ route('delivery.zones.store') }}" class="grid gap-3 sm:grid-cols-5">@csrf
                <div class="sm:col-span-2"><x-ui.input name="name" :label="__('orders.zone_name')" required maxlength="80" /></div>
                <x-ui.input name="fee" type="number" step="0.01" min="0" :label="__('orders.delivery_fee')" />
                <x-ui.input name="min_order" type="number" step="0.01" min="0" :label="__('orders.delivery_min')" />
                <div class="flex items-end"><x-ui.button :block="false">{{ __('orders.zone_add') }}</x-ui.button></div>
            </form>
        </x-ui.card>

        @if ($zones->isEmpty())
            <div class="card"><x-ui.empty icon="store" :title="__('orders.zones_empty')" :text="__('orders.zones_empty_text')" /></div>
        @else
            <ul class="grid gap-3">
                @foreach ($zones as $z)
                    <li class="card p-4">
                        <form method="POST" action="{{ route('delivery.zones.update', $z->id) }}" class="grid items-end gap-3 sm:grid-cols-6">@csrf @method('PUT')
                            <div class="sm:col-span-2"><x-ui.input name="name" :label="__('orders.zone_name')" :value="$z->name" required maxlength="80" /></div>
                            <x-ui.input name="fee" type="number" step="0.01" min="0" :label="__('orders.delivery_fee')" :value="$z->fee" />
                            <x-ui.input name="min_order" type="number" step="0.01" min="0" :label="__('orders.delivery_min')" :value="$z->min_order" />
                            <x-ui.input name="eta_minutes" type="number" min="0" max="240" :label="__('orders.zone_eta')" :value="$z->eta_minutes" />
                            <div class="flex items-center gap-2"><label class="flex items-center gap-1.5 text-sm"><input type="checkbox" name="is_active" value="1" class="check" @checked($z->is_active)>{{ __('orders.zone_active') }}</label>
                                <x-ui.button :block="false" size="sm" variant="secondary">{{ __('admin.save') }}</x-ui.button></div>
                        </form>
                        <form method="POST" action="{{ route('delivery.zones.destroy', $z->id) }}" class="mt-2 text-end" onsubmit="return confirm('{{ __('menu.delete_confirm') }}')">@csrf @method('DELETE')<button class="text-sm text-red-600">{{ __('admin.delete') }}</button></form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.app>
