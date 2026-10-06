<x-layouts.admin :title="__('admin.nav.invoices')">
    <form method="GET" class="mb-4 flex items-end gap-3">
        <x-ui.select name="status" :label="__('admin.status')" :value="$status" :placeholder="__('admin.all')" :options="collect(['open', 'paid', 'void', 'refunded'])->mapWithKeys(fn ($s) => [$s => __('billing.status_'.$s)])->all()" />
        <x-ui.button class="!w-auto">{{ __('admin.filter') }}</x-ui.button>
    </form>
    <x-ui.card class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-sm">
            <thead class="text-gray-500"><tr><th class="py-2 text-start">{{ __('billing.invoice') }}</th><th class="text-start">{{ __('admin.restaurants.name') }}</th><th class="text-start">{{ __('billing.total') }}</th><th class="text-start">{{ __('admin.status') }}</th><th class="text-start">{{ __('billing.issued') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($invoices as $invoice)
                <tr class="border-t border-gray-100 dark:border-gray-800">
                    <td class="py-2 font-medium">{{ $invoice->number }}</td>
                    <td>{{ $restaurants[$invoice->restaurant_id] ?? '#'.$invoice->restaurant_id }}</td>
                    <td>{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency_code }}</td>
                    <td>{{ __('billing.status_'.$invoice->status) }}</td>
                    <td>{{ $invoice->issued_at->toDateString() }}</td>
                    <td class="whitespace-nowrap text-end">
                        <a class="text-brand-600 hover:underline" href="{{ route('admin.invoices.pdf', $invoice->id) }}">PDF</a>
                        @if ($invoice->status === 'open')
                            <form method="POST" action="{{ route('admin.invoices.paid', $invoice->id) }}" class="ms-2 inline">@csrf<button class="text-brand-600 hover:underline">{{ __('admin.invoices.mark_paid') }}</button></form>
                            <form method="POST" action="{{ route('admin.invoices.void', $invoice->id) }}" class="ms-2 inline" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf<button class="text-red-600 hover:underline">{{ __('admin.invoices.void') }}</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-4 text-gray-500">{{ __('admin.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $invoices->links() }}</div>
    </x-ui.card>
</x-layouts.admin>
