<x-layouts.app :title="__('tips.title')">
    @php($money = fn (int $c) => $restaurant->money($c / 100))
    @php($ranges = ['7' => 'range_7', '30' => 'range_30', '90' => 'range_90'])
    <x-ui.page-header :title="__('tips.title')" :description="__('tips.subtitle')">
        <x-slot:actions><a href="{{ route('tips.export', request()->only(['range', 'from', 'to'])) }}" class="btn btn-secondary"><x-ui.icon name="download" size="4" />{{ __('tips.export') }}</a></x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))<x-ui.alert type="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif

    <div class="mb-5 flex flex-wrap items-center gap-2" x-data="{ custom: {{ $period['key'] === 'custom' ? 'true' : 'false' }} }">
        @foreach ($ranges as $key => $label)<a href="{{ route('tips.index', ['range' => $key]) }}" class="chip {{ $period['key'] === (string) $key ? 'chip-active' : '' }}">{{ __('analytics.'.$label) }}</a>@endforeach
        <button type="button" class="chip {{ $period['key'] === 'custom' ? 'chip-active' : '' }}" x-on:click="custom = !custom">{{ __('analytics.range_custom') }}</button>
        <form x-show="custom" x-cloak method="GET" action="{{ route('tips.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="range" value="custom">
            <input type="date" name="from" value="{{ $period['from']->toDateString() }}" class="field !w-auto" aria-label="{{ __('analytics.from') }}">
            <input type="date" name="to" value="{{ $period['to']->toDateString() }}" class="field !w-auto" aria-label="{{ __('analytics.to') }}">
            <button class="btn btn-secondary btn-sm">{{ __('analytics.apply') }}</button>
        </form>
    </div>

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <x-ui.card><p class="text-sm text-muted">{{ __('tips.collected') }}</p><p class="tnum mt-1 text-2xl font-bold"><bdi>{{ $money($split['total']) }}</bdi></p><p class="mt-1 text-xs text-muted">{{ $period['from']->toDateString() }} → {{ $period['to']->toDateString() }}</p></x-ui.card>
        <x-ui.card><p class="text-sm text-muted">{{ __('tips.house') }} ({{ rtrim(rtrim(number_format($house, 2, '.', ''), '0'), '.') }}%)</p><p class="tnum mt-1 text-2xl font-bold"><bdi>{{ $money($split['house']) }}</bdi></p></x-ui.card>
        <x-ui.card><p class="text-sm text-muted">{{ __('tips.pool') }}</p><p class="tnum mt-1 text-2xl font-bold"><bdi>{{ $money($split['pool']) }}</bdi></p></x-ui.card>
    </div>

    <form method="POST" action="{{ route('tips.update') }}" class="max-w-3xl">
        @csrf @method('PUT')
        <input type="hidden" name="range" value="{{ $period['key'] }}">
        @if ($period['key'] === 'custom')<input type="hidden" name="from" value="{{ $period['from']->toDateString() }}"><input type="hidden" name="to" value="{{ $period['to']->toDateString() }}">@endif
        <x-ui.card :title="__('tips.team')" :description="__('tips.team_help')">
            @if ($split['pool'] === 0 && $split['total'] > 0 && collect($rows)->sum('weight') <= 0)<x-ui.alert type="warning" class="mb-3">{{ __('tips.nobody') }}</x-ui.alert>@endif
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="text-muted"><th class="py-1 text-start">{{ __('tips.staff') }}</th><th class="w-28 text-end">{{ __('tips.weight') }}</th><th class="w-36 text-end">{{ __('tips.share') }}</th></tr></thead>
                <tbody>@foreach ($rows as $r)
                    <tr class="border-t border-slate-200 dark:border-slate-800"><td class="py-2">{{ $r['name'] }}</td>
                        <td class="text-end">@can('payments.manage')<input type="number" name="weights[{{ $r['id'] }}]" value="{{ $r['weight'] + 0 }}" min="0" max="100" step="0.25" class="field tnum !w-24 text-end" aria-label="{{ $r['name'] }}">@else<span class="tnum">{{ $r['weight'] + 0 }}</span>@endcan</td>
                        <td class="tnum text-end font-semibold"><bdi>{{ $money($r['share']) }}</bdi></td></tr>
                @endforeach</tbody>
            </table></div>
            @can('payments.manage')
                <div class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                    <div class="w-48"><x-ui.input name="house_percent" type="number" min="0" max="100" step="0.5" :label="__('tips.house_percent')" :value="$house + 0" /></div>
                    <x-ui.button :block="false">{{ __('tips.recalculate') }}</x-ui.button>
                </div>
            @endcan
        </x-ui.card>
    </form>
</x-layouts.app>
