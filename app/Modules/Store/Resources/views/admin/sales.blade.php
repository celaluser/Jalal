<x-layouts.admin :title="__('store.admin.sales')">
    <x-ui.page-header :title="__('store.admin.sales')" :description="__('store.admin.sales_sub')" :back="['url' => route('admin.store.index'), 'label' => __('store.admin.title')]" />
    @if (session('status'))<x-ui.alert type="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif

    <div class="mb-5 grid gap-3 sm:grid-cols-3">
        @forelse ($revenue as $r)
            <x-ui.card><p class="text-sm text-muted">{{ __('store.admin.revenue') }} ({{ $r->currency_code }})</p><p class="display text-2xl font-semibold tnum">{{ number_format((float) $r->total, 2) }}</p><p class="text-xs text-muted">{{ trans_choice('store.admin.paid_invoices', $r->n, ['count' => $r->n]) }}</p></x-ui.card>
        @empty
            <x-ui.card><p class="text-sm text-muted">{{ __('store.admin.no_revenue') }}</p></x-ui.card>
        @endforelse
    </div>

    <x-ui.card :title="__('store.admin.grant')" class="mb-5">
        <form method="POST" action="{{ route('admin.store.grant') }}" class="grid items-end gap-3 sm:grid-cols-4">
            @csrf
            <div><label for="restaurant_id" class="mb-1.5 block text-sm font-medium">{{ __('store.admin.restaurant') }}</label><select id="restaurant_id" name="restaurant_id" class="field" required>@foreach ($allRestaurants as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
            <div><label for="item" class="mb-1.5 block text-sm font-medium">{{ __('store.admin.item') }}</label><select id="item" name="item" class="field" required>@foreach ($items as $i)<option value="{{ $i['slug'] }}">{{ $i['name'] }} ({{ __('store.admin.kind_'.$i['kind']) }})</option>@endforeach</select></div>
            <x-ui.input name="months" type="number" min="1" max="120" :label="__('store.admin.months')" :hint="__('store.admin.months_hint')" />
            <x-ui.button :block="false">{{ __('store.admin.grant_button') }}</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.table>
        <thead><tr><th>{{ __('store.admin.restaurant') }}</th><th>{{ __('store.admin.item') }}</th><th>{{ __('store.admin.source') }}</th><th>{{ __('store.admin.status') }}</th><th>{{ __('store.admin.ends') }}</th><th></th></tr></thead>
        <tbody>
            @foreach ($rows as $e)
                <tr>
                    <td>{{ $restaurants[$e->restaurant_id] ?? '#'.$e->restaurant_id }}</td>
                    <td>{{ $items[$e->item_slug]['name'] ?? $e->item_slug }}</td>
                    <td>{{ __('store.source_'.$e->source) }}</td>
                    <td><x-ui.badge :tone="$e->status === 'active' && (! $e->ends_at || $e->ends_at->isFuture()) ? 'success' : null">{{ $e->status === 'active' && $e->ends_at && $e->ends_at->isPast() ? __('store.admin.status_expired') : __('store.admin.status_'.$e->status) }}</x-ui.badge></td>
                    <td class="tnum">{{ $e->ends_at ? $e->ends_at->isoFormat('LL') : __('store.valid_forever') }}</td>
                    <td class="text-end">@if ($e->status === 'active')<form method="POST" action="{{ route('admin.store.revoke', $e->id) }}" onsubmit="return confirm(@js(__('store.admin.confirm_revoke')))">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">{{ __('store.admin.revoke') }}</button></form>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
</x-layouts.admin>
