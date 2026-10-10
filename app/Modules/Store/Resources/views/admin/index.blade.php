@php($money = fn ($i) => \App\Modules\Core\Models\Currency::where('code', $i['currency'])->first()?->format($i['price']) ?? number_format($i['price'], 2).' '.$i['currency'])
<x-layouts.admin :title="__('store.admin.title')">
    <x-ui.page-header :title="__('store.admin.title')" :description="__('store.admin.subtitle')">
        <x-slot:actions><a href="{{ route('admin.store.sales') }}" class="btn btn-secondary">{{ __('store.admin.sales') }}</a></x-slot:actions>
    </x-ui.page-header>
    @if (session('status'))<x-ui.alert type="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif

    <div class="mb-4 flex flex-wrap gap-1 rounded-xl bg-surface-2 p-1 text-sm font-semibold sm:w-fit">
        @foreach ([null => 'all', 'feature' => 'features', 'theme' => 'themes'] as $k => $label)
            <a href="{{ route('admin.store.index', $k ? ['kind' => $k] : []) }}" @class(['rounded-lg px-4 py-2', 'bg-surface shadow-sm' => $kind === $k, 'text-muted' => $kind !== $k])>{{ __('store.admin.tab_'.$label) }}</a>
        @endforeach
    </div>

    <x-ui.table>
        <thead><tr><th>{{ __('store.admin.item') }}</th><th>{{ __('store.admin.mode') }}</th><th>{{ __('store.admin.price') }}</th><th>{{ __('store.admin.owners') }}</th><th></th></tr></thead>
        <tbody>
            @foreach ($items as $i)
                <tr>
                    <td><p class="font-medium">{{ $i['name'] }}</p><p class="text-xs text-muted">{{ __('store.admin.kind_'.$i['kind']) }}@unless ($i['visible']) · {{ __('store.admin.hidden') }}@endunless</p></td>
                    <td><x-ui.badge :tone="$i['mode'] === 'paid' ? 'warning' : ($i['mode'] === 'free' ? 'success' : null)">{{ __('store.admin.mode_'.$i['mode']) }}</x-ui.badge></td>
                    <td class="tnum">@if ($i['mode'] === 'paid')<bdi>{{ $money($i) }}</bdi> <span class="text-xs text-muted">{{ __('store.per_'.$i['billing']) }}</span>@else<span class="text-muted">—</span>@endif</td>
                    <td class="tnum">{{ $owners[$i['slug']] ?? 0 }}</td>
                    <td class="text-end"><a href="{{ route('admin.store.edit', $i['slug']) }}" class="btn btn-secondary btn-sm">{{ __('admin.edit') }}</a></td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
</x-layouts.admin>
