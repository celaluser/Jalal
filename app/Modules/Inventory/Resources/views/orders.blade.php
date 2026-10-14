<x-layouts.app :title="__('inventory.po_title')">
    <x-ui.page-header :title="__('inventory.po_title')" :description="__('inventory.po_subtitle')">
        <x-slot:actions><a href="{{ route('inventory.index') }}" class="btn btn-secondary">{{ __('inventory.title') }}</a></x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))<x-ui.alert type="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif
    @if ($errors->any())<x-ui.alert type="danger" class="mb-4">{{ $errors->first() }}</x-ui.alert>@endif

    @forelse ($groups as $sid => $g)
        <form method="POST" action="{{ route('inventory.orders.pdf') }}" class="card card-pad mb-4 space-y-3">
            @csrf
            <input type="hidden" name="supplier_id" value="{{ $sid }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold">{{ $g['supplier']?->name ?? __('inventory.no_supplier') }}</h2>
                <span class="text-sm text-muted">{{ $g['supplier']?->email ?: __('inventory.po_no_email') }}</span>
            </div>
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="text-start text-muted"><th class="py-1 text-start">{{ __('inventory.name') }}</th><th class="text-end">{{ __('inventory.stock') }}</th><th class="w-36 text-end">{{ __('inventory.quantity') }}</th></tr></thead>
                <tbody>@foreach ($g['lines'] as $l)
                    <tr class="border-t border-slate-200 dark:border-slate-800"><td class="py-2">{{ $l['name'] }}</td><td class="tnum text-end">{{ $l['stock'] + 0 }} {{ $l['unit'] }}</td>
                        <td class="text-end"><input type="number" name="qty[{{ $l['id'] }}]" value="{{ $l['qty'] + 0 }}" min="0" step="0.001" class="field tnum !w-28 text-end" aria-label="{{ $l['name'] }}"></td></tr>
                @endforeach</tbody>
            </table></div>
            <x-ui.input name="note" :label="__('inventory.po_note')" maxlength="500" />
            <div class="flex flex-wrap gap-2">
                <button class="btn btn-secondary">{{ __('inventory.po_download') }}</button>
                @if ($g['supplier']?->email)<button class="btn btn-primary" formaction="{{ route('inventory.orders.mail') }}">{{ __('inventory.po_send') }}</button>@endif
            </div>
        </form>
    @empty
        <div class="card"><x-ui.empty icon="clipboard" :title="__('inventory.po_empty_title')" :text="__('inventory.po_empty_text')" /></div>
    @endforelse
</x-layouts.app>
