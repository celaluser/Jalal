<x-layouts.app :title="__('orders.courier_title')">
    <x-ui.page-header :title="__('orders.courier_title')" :description="__('orders.courier_sub')" />
    @error('order')<x-ui.alert type="error" class="mb-4">{{ $message }}</x-ui.alert>@enderror
    @php($money = fn (int $c) => $restaurant->money($c / 100))

    <div class="max-w-2xl space-y-6">
        <section>
            <h2 class="mb-2 font-semibold">{{ __('orders.courier_mine') }}</h2>
            @forelse ($mine as $o)
                <article class="card mb-3 p-4">
                    <div class="flex items-start justify-between gap-3"><p class="display text-xl font-bold">{{ $o->label() }}</p><p class="tnum font-semibold"><bdi>{{ $money($o->total_cents) }}</bdi></p></div>
                    <p class="mt-1 font-medium">{{ $o->customer_name }}</p>
                    <p class="text-sm">{{ $o->delivery_address }}@if ($o->delivery_zone) · {{ $o->delivery_zone }}@endif</p>
                    <p class="mt-1 text-sm"><a class="link" href="tel:{{ $o->customer_phone }}" dir="ltr">{{ $o->customer_phone }}</a>
                        · <a class="link" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($o->delivery_address) }}">{{ __('orders.courier_map') }}</a></p>
                    @if ($o->note)<p class="mt-2 rounded-lg bg-brand-100 px-3 py-2 text-sm text-brand-900 dark:bg-brand-900/40 dark:text-brand-100">{{ $o->note }}</p>@endif
                    <ul class="mt-2 text-sm text-muted">@foreach ($o->items as $i)<li>{{ $i->qty }}× {{ $i->name }}</li>@endforeach</ul>
                    <p class="mt-2 text-sm font-medium">{{ $o->isPaid() ? __('orders.paid') : __('orders.courier_collect', ['amount' => $money($o->total_cents - $o->paid_cents)]) }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @unless ($o->dispatched_at)<form method="POST" action="{{ route('courier.leave', $o->id) }}">@csrf<x-ui.button variant="secondary" :block="false">{{ __('orders.dispatch') }}</x-ui.button></form>@endunless
                        @unless ($o->isPaid())
                            <form method="POST" action="{{ route('courier.delivered', $o->id) }}">@csrf<input type="hidden" name="collected" value="cash"><x-ui.button :block="false">{{ __('orders.courier_cash') }}</x-ui.button></form>
                            <form method="POST" action="{{ route('courier.delivered', $o->id) }}">@csrf<input type="hidden" name="collected" value="card"><x-ui.button :block="false">{{ __('orders.courier_card') }}</x-ui.button></form>
                        @else
                            <form method="POST" action="{{ route('courier.delivered', $o->id) }}">@csrf<x-ui.button :block="false">{{ __('orders.courier_delivered') }}</x-ui.button></form>
                        @endunless
                    </div>
                </article>
            @empty
                <p class="text-sm text-muted">{{ __('orders.courier_none') }}</p>
            @endforelse
            @if ($preparing->isNotEmpty())<p class="text-sm text-muted">{{ trans_choice('orders.courier_coming', $preparing->count(), ['count' => $preparing->count()]) }}</p>@endif
        </section>

        <section>
            <h2 class="mb-2 font-semibold">{{ __('orders.courier_open') }}</h2>
            @forelse ($open as $o)
                <article class="card mb-3 flex items-center justify-between gap-3 p-4">
                    <div class="min-w-0"><p class="font-bold">{{ $o->label() }} <span class="tnum font-normal text-muted"><bdi>{{ $money($o->total_cents) }}</bdi></span></p><p class="truncate text-sm text-muted">{{ $o->delivery_address }}</p></div>
                    <form method="POST" action="{{ route('courier.claim', $o->id) }}">@csrf<x-ui.button variant="secondary" :block="false" size="sm">{{ __('orders.courier_take') }}</x-ui.button></form>
                </article>
            @empty
                <p class="text-sm text-muted">{{ __('orders.courier_nothing_open') }}</p>
            @endforelse
        </section>
    </div>

    {{-- While a delivery is on its way, the phone reports its position so the guest can follow on the map. Nothing is sent unless the courier turns it on. --}}
    @php($onTheWay = $mine->filter(fn ($o) => $o->dispatched_at)->pluck('id')->values())
    @if ($onTheWay->isNotEmpty())
        <div class="mt-6 max-w-2xl" x-data="courierShare(@js(['orders' => $onTheWay, 'url' => url('courier'), 'csrf' => csrf_token(), 'on' => __('orders.courier_sharing'), 'off' => __('orders.courier_share'), 'failed' => __('orders.location_failed')]))">
            <button type="button" class="btn btn-secondary btn-sm" x-on:click="toggle()"><span aria-hidden="true">📍</span> <span x-text="active ? cfg.on : cfg.off"></span></button>
            <p class="mt-1 text-xs text-muted">{{ __('orders.courier_share_help') }}</p>
            <p class="mt-1 text-sm text-red-600" x-show="error" x-text="error" role="alert"></p>
        </div>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('courierShare', (cfg) => ({
                    cfg, active: false, error: '', watch: null, last: 0,
                    toggle() { this.active ? this.stop() : this.start(); },
                    start() {
                        if (!navigator.geolocation) { this.error = cfg.failed; return; }
                        this.error = '';
                        this.watch = navigator.geolocation.watchPosition((p) => this.send(p), () => { this.error = cfg.failed; this.stop(); }, { enableHighAccuracy: true, maximumAge: 10000, timeout: 20000 });
                        this.active = true;
                    },
                    stop() { if (this.watch !== null) { navigator.geolocation.clearWatch(this.watch); } this.watch = null; this.active = false; },
                    send(p) {
                        if (Date.now() - this.last < 15000) { return; }
                        this.last = Date.now();
                        cfg.orders.forEach((id) => fetch(cfg.url + '/' + id + '/ping', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf }, body: JSON.stringify({ lat: p.coords.latitude, lng: p.coords.longitude }) }).catch(() => {}));
                    },
                }));
            });
        </script>
    @endif
</x-layouts.app>
