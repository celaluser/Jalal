<x-layouts.admin :title="__('admin.nav.invoices')">
    <x-ui.page-header :title="__('admin.nav.invoices')" :description="__('admin.invoices.description')" />
    <form method="GET" class="card flex flex-wrap items-end gap-3 p-4">
        <div class="w-48"><x-ui.select name="status" :label="__('admin.status')" :value="$status" :placeholder="__('admin.all')" :options="collect(['open', 'paid', 'void', 'refunded'])->mapWithKeys(fn ($s) => [$s => __('billing.status_'.$s)])->all()" /></div>
        <x-ui.button :block="false" icon="search">{{ __('admin.filter') }}</x-ui.button>
    </form>
    <x-ui.table>
        <thead><tr><th>{{ __('billing.invoice') }}</th><th>{{ __('admin.restaurants.name') }}</th><th class="text-end">{{ __('billing.total') }}</th><th>{{ __('admin.status') }}</th><th>{{ __('billing.issued') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($invoices as $invoice)
            <tr>
                <td class="tnum font-medium">{{ $invoice->number }}</td>
                <td class="text-muted">{{ $restaurants[$invoice->restaurant_id] ?? '#'.$invoice->restaurant_id }}</td>
                <td class="tnum text-end">{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency_code }}</td>
                <td><x-ui.status :value="$invoice->status" :label="__('billing.status_'.$invoice->status)" /></td>
                <td class="tnum text-muted">{{ $invoice->issued_at->toDateString() }}</td>
                <td class="whitespace-nowrap text-end">
                    <a class="btn btn-ghost btn-sm" href="{{ route('admin.invoices.pdf', $invoice->id) }}"><x-ui.icon name="download" size="4" />PDF</a>
                    @if ($invoice->status === 'open')
                        <form method="POST" action="{{ route('admin.invoices.paid', $invoice->id) }}" class="inline">@csrf<button class="btn btn-secondary btn-sm"><x-ui.icon name="check" size="4" />{{ __('admin.invoices.mark_paid') }}</button></form>
                        <form method="POST" action="{{ route('admin.invoices.void', $invoice->id) }}" class="inline" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf<button class="btn btn-ghost btn-sm text-red-600 dark:text-red-400">{{ __('admin.invoices.void') }}</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr class="hover:!bg-transparent"><td colspan="6"><x-ui.empty icon="receipt" :title="__('admin.empty')" /></td></tr>
        @endforelse
        </tbody>
        <x-slot:footer>{{ $invoices->links() }}</x-slot:footer>
    </x-ui.table>
</x-layouts.admin>
