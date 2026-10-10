<x-layouts.app :title="__('orders.history_title')">
    <x-ui.page-header :title="__('orders.history_title')" :description="__('orders.history_sub')" :back="['url' => route('orders.board'), 'label' => __('orders.board_title')]">
        <x-slot:actions><a class="btn btn-secondary" href="{{ route('orders.history.export', $filters) }}"><x-ui.icon name="download" size="4" />{{ __('menu.export_csv') }}</a></x-slot:actions>
    </x-ui.page-header>
    @php($money = fn (int $c) => $restaurant->money($c / 100))

    <form method="GET" class="card card-pad mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.input name="q" :label="__('orders.history_search')" :value="$filters['q'] ?? ''" maxlength="80" />
        <x-ui.input name="from" type="date" :label="__('orders.history_from')" :value="$filters['from'] ?? ''" />
        <x-ui.input name="to" type="date" :label="__('orders.history_to')" :value="$filters['to'] ?? ''" />
        <x-ui.select name="status" :label="__('orders.history_status')" :options="collect([...\App\Modules\Orders\Support\OrderStatus::FLOW, 'cancelled'])->mapWithKeys(fn ($s) => [$s => __('orders.status_'.$s)])->all()" :value="$filters['status'] ?? ''" placeholder="—" />
        <x-ui.select name="type" :label="__('orders.history_type')" :options="collect(\App\Modules\Orders\Support\OrderType::ALL)->mapWithKeys(fn ($t) => [$t => __('orders.type_'.$t)])->all()" :value="$filters['type'] ?? ''" placeholder="—" />
        <x-ui.select name="paid" :label="__('orders.history_paid')" :options="['yes' => __('orders.paid'), 'no' => __('orders.unpaid')]" :value="$filters['paid'] ?? ''" placeholder="—" />
        <x-ui.select name="source" :label="__('orders.history_source')" :options="['qr' => __('orders.source_qr'), 'staff' => __('orders.source_staff'), 'kiosk' => __('orders.source_kiosk')]" :value="$filters['source'] ?? ''" placeholder="—" />
        <x-ui.select name="table" :label="__('orders.pos_table')" :options="$tables->mapWithKeys(fn ($t) => [$t->id => table_label($t->name)])->all()" :value="$filters['table'] ?? ''" placeholder="—" />
        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4"><x-ui.button :block="false">{{ __('orders.history_filter') }}</x-ui.button><a class="btn btn-ghost" href="{{ route('orders.history') }}">{{ __('customer.reset_filters') }}</a></div>
    </form>

    <p class="mb-3 text-sm text-muted tnum"><bdi>{{ trans_choice('orders.history_summary', $totals['count'], ['count' => $totals['count'], 'sum' => $money($totals['sum']), 'tips' => $money($totals['tips'])]) }}</bdi></p>

    @if ($orders->isEmpty())
        <div class="card"><x-ui.empty icon="inbox" :title="__('orders.history_empty')" :text="__('orders.history_empty_text')" /></div>
    @else
        <x-ui.table>
            <thead><tr><th>#</th><th>{{ __('orders.history_when') }}</th><th>{{ __('orders.history_type') }}</th><th>{{ __('orders.customer') }}</th><th>{{ __('orders.history_status') }}</th><th class="text-end">{{ __('orders.total') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($orders as $o)
                    <tr>
                        <td class="font-semibold"><a class="link" href="{{ route('orders.show', $o->id) }}">{{ $o->label() }}</a></td>
                        <td class="tnum text-muted">{{ $o->created_at->toDayDateTimeString() }}</td>
                        <td>{{ __('orders.type_'.$o->type) }}@if ($o->table_name) · {{ table_label($o->table_name) }}@endif</td>
                        <td>{{ $o->customer_name ?: '—' }}</td>
                        <td><x-ui.status :value="$o->status" :label="__('orders.status_'.$o->status)" /></td>
                        <td class="tnum text-end"><bdi>{{ $money($o->total_cents) }}</bdi> <x-ui.badge :tone="$o->isPaid() ? 'success' : 'neutral'">{{ $o->isPaid() ? __('orders.paid') : __('orders.unpaid') }}</x-ui.badge></td>
                        <td><a class="btn btn-ghost btn-sm" href="{{ route('orders.show', $o->id) }}" aria-label="{{ __('orders.details') }}"><x-ui.icon name="chevron-right" size="4" /></a></td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</x-layouts.app>
