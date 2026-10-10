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
</x-layouts.app>
