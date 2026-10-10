<x-layouts.app :title="__('orders.zones_title')">
    <x-ui.page-header :title="__('orders.zones_title')" :description="__('orders.zones_sub')" />
    @php($restaurant = auth()->user()->restaurant)
    <div class="max-w-3xl space-y-5">
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
