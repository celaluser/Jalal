<x-layouts.app :title="__('hub.title')">
    <x-ui.page-header :title="__('hub.title')" :description="__('hub.sub')" />

    <div class="max-w-3xl space-y-5">
        @if ($secret)
            <x-ui.alert type="warning">
                <p class="font-semibold">{{ __('hub.secret_title') }}</p>
                <p class="mt-1 text-sm">{{ __('hub.secret_text') }}</p>
                <code class="mt-2 block break-all rounded bg-surface p-2 text-xs" dir="ltr">{{ $secret['url'] }}</code>
            </x-ui.alert>
        @endif

        <x-ui.card :title="__('hub.add')">
            <form method="POST" action="{{ route('channels.store') }}" class="grid gap-3 sm:grid-cols-5">@csrf
                <div class="sm:col-span-2"><x-ui.input name="name" :label="__('hub.name')" :placeholder="__('hub.name_example')" required maxlength="80" /></div>
                <div class="sm:col-span-2"><x-ui.select name="kind" :label="__('hub.kind')" :options="$kinds" /></div>
                <div class="flex items-end"><x-ui.button :block="false">{{ __('hub.create') }}</x-ui.button></div>
            </form>
        </x-ui.card>

        @if ($channels->isEmpty())
            <div class="card"><x-ui.empty icon="zap" :title="__('hub.empty')" :text="__('hub.empty_text')" /></div>
        @else
            <ul class="grid gap-3">
                @foreach ($channels as $c)
                    <li class="card p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold">{{ $c->name }} <x-ui.badge :tone="$c->is_active ? 'success' : 'neutral'">{{ $c->is_active ? __('hub.active') : __('hub.paused') }}</x-ui.badge></p>
                                <p class="mt-0.5 text-xs text-muted"><bdi>{{ trans_choice('hub.orders_count', $c->orders_count, ['count' => $c->orders_count]) }}</bdi>
                                    @if ($c->last_received_at) · {{ __('hub.last', ['when' => $c->last_received_at->diffForHumans()]) }}@endif
                                    · <span dir="ltr">…{{ $c->token_hint }}</span></p>
                                @if ($c->last_error)<p class="mt-1 text-xs text-red-600">{{ __('hub.last_error', ['error' => $c->last_error]) }}</p>@endif
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('channels.toggle', $c->id) }}">@csrf<x-ui.button variant="secondary" size="sm" :block="false">{{ $c->is_active ? __('hub.pause') : __('hub.resume') }}</x-ui.button></form>
                                <form method="POST" action="{{ route('channels.renew', $c->id) }}" onsubmit="return confirm('{{ __('hub.renew_confirm') }}')">@csrf<x-ui.button variant="secondary" size="sm" :block="false">{{ __('hub.renew') }}</x-ui.button></form>
                                <form method="POST" action="{{ route('channels.destroy', $c->id) }}" onsubmit="return confirm('{{ __('menu.delete_confirm') }}')">@csrf @method('DELETE')<button class="px-2 text-sm text-red-600">{{ __('admin.delete') }}</button></form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <x-ui.card :title="__('hub.how_title')">
            <p class="text-sm text-muted">{{ __('hub.how_text') }}</p>
            <pre class="mt-3 overflow-x-auto rounded-lg bg-surface-2 p-3 text-xs" dir="ltr">POST {{ url('hub') }}/&lt;secret&gt;
Content-Type: application/json

{
  "external_id": "YS-48213",
  "type": "delivery",
  "customer_name": "Ayşe Yılmaz",
  "customer_phone": "+90 555 111 22 33",
  "delivery_address": "Moda Cd. 12/3, Kadıköy",
  "paid": true,
  "note": "Ring the bell",
  "lines": [
    { "name": "Margherita", "qty": 2 },
    { "product_id": 17, "qty": 1, "note": "no onion" }
  ]
}</pre>
            <p class="mt-3 text-xs text-muted">{{ __('hub.how_rules') }}</p>
        </x-ui.card>
    </div>
</x-layouts.app>
