<x-layouts.app :title="__('menu.import_title')">
    <x-ui.page-header :title="__('menu.import_title')" :description="__('menu.import_sub')" :back="['url' => route('menu.index'), 'label' => __('menu.title')]">
        <x-slot:actions><a href="{{ route('menu.import.sample') }}" class="btn btn-secondary"><x-ui.icon name="download" size="4" />{{ __('menu.import_sample') }}</a></x-slot:actions>
    </x-ui.page-header>

    @error('file')<x-ui.alert type="error" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    @if ($preview)
        <div class="mb-5 grid gap-3 sm:grid-cols-4">
            <x-ui.stat :label="__('menu.import_new_dishes')" :value="$preview['new_products']" />
            <x-ui.stat :label="__('menu.import_updates')" :value="$preview['updates']" />
            <x-ui.stat :label="__('menu.import_new_categories')" :value="$preview['new_categories']" />
            <x-ui.stat :label="__('menu.import_problems')" :value="count($preview['errors'])" />
        </div>

        @if ($preview['errors'])
            <x-ui.alert type="warning" class="mb-4"><div><p class="font-semibold">{{ __('menu.import_skipped') }}</p>
                <ul class="mt-1 list-disc ps-5">@foreach (array_slice($preview['errors'], 0, 15) as $e)<li>{{ __('menu.import_line', ['line' => $e['line']]) }}: {{ __('menu.import_err_'.$e['message']) }}</li>@endforeach</ul></div></x-ui.alert>
        @endif

        @if ($preview['total'])
            <x-ui.table class="mb-5">
                <thead><tr><th>{{ __('menu.category') }}</th><th>{{ __('menu.name') }}</th><th class="text-end">{{ __('menu.price') }}</th><th>{{ __('menu.stock_col_stock') }}</th><th></th></tr></thead>
                <tbody>@foreach ($preview['sample'] as $r)
                    <tr><td>{{ $r['category'] }}</td><td class="font-medium">{{ $r['dish'] }}</td><td class="tnum text-end">{{ number_format($r['price'], 2) }}</td><td class="tnum">{{ $r['stock'] ?? '—' }}</td>
                        <td><x-ui.badge :tone="$r['exists'] ? 'info' : 'success'">{{ $r['exists'] ? __('menu.import_update') : __('menu.import_new') }}</x-ui.badge></td></tr>
                @endforeach</tbody>
            </x-ui.table>
            @if ($preview['total'] > count($preview['sample']))<p class="mb-4 text-sm text-muted">{{ __('menu.import_more', ['count' => $preview['total'] - count($preview['sample'])]) }}</p>@endif
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('menu.import.apply') }}">@csrf<x-ui.button :block="false">{{ __('menu.import_apply', ['count' => $preview['total']]) }}</x-ui.button></form>
                <form method="POST" action="{{ route('menu.import.cancel') }}">@csrf<button class="btn btn-secondary">{{ __('admin.cancel') }}</button></form>
            </div>
        @else
            <form method="POST" action="{{ route('menu.import.cancel') }}">@csrf<button class="btn btn-secondary">{{ __('menu.import_try_again') }}</button></form>
        @endif
    @else
        <form method="POST" action="{{ route('menu.import.preview') }}" enctype="multipart/form-data" class="card card-pad max-w-xl space-y-4">
            @csrf
            <div>
                <label for="file" class="mb-1.5 block text-sm font-medium">{{ __('menu.import_file') }}</label>
                <input id="file" type="file" name="file" accept=".csv,text/csv,text/plain" required class="field !py-2">
                <p class="mt-1.5 text-xs text-muted">{{ __('menu.import_columns') }}</p>
            </div>
            <x-ui.button :block="false">{{ __('menu.import_check') }}</x-ui.button>
        </form>
    @endif
</x-layouts.app>
