@php
    $money = fn (int $cents) => $restaurant->money($cents / 100);
    $tone = ['star' => 'success', 'plowhorse' => 'warning', 'puzzle' => 'info', 'dog' => 'danger'];
    $ranges = ['7' => 'range_7', '30' => 'range_30', '90' => 'range_90'];
@endphp
<x-layouts.app :title="__('analytics.eng_title')">
    <x-ui.page-header :title="__('analytics.eng_title')" :description="__('analytics.eng_sub', ['from' => $period['from']->translatedFormat('M j, Y'), 'to' => $period['to']->translatedFormat('M j, Y')])" :back="['url' => route('reports.index'), 'label' => __('analytics.title')]">
        <x-slot:actions><a href="{{ route('reports.menu.export', request()->only('range')) }}" class="btn btn-secondary"><x-ui.icon name="download" size="4" />{{ __('analytics.export') }}</a></x-slot:actions>
    </x-ui.page-header>

    <div class="mb-5 flex flex-wrap gap-2">@foreach ($ranges as $key => $label)<a href="{{ route('reports.menu', ['range' => $key]) }}" class="chip {{ $period['key'] === (string) $key ? 'chip-active' : '' }}">{{ __('analytics.'.$label) }}</a>@endforeach</div>

    @if ($data['items'] === [] && $data['unclassified'] === [])
        <div class="card"><x-ui.empty icon="activity" :title="__('analytics.empty_title')" :text="__('analytics.empty_text')" /></div>
    @else
        <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (\App\Modules\Analytics\Services\MenuEngineering::CLASSES as $c)
                <div class="card card-pad"><x-ui.badge :tone="$tone[$c]" dot>{{ __('analytics.class_'.$c) }}</x-ui.badge>
                    <p class="display tnum mt-2 text-3xl font-bold">{{ collect($data['items'])->where('class', $c)->count() }}</p>
                    <p class="mt-1 text-xs text-muted">{{ __('analytics.class_'.$c.'_tip') }}</p></div>
            @endforeach
        </div>
        @if ($data['food_cost_percent'] !== null)<p class="mb-5 text-sm text-muted">{{ __('analytics.food_cost', ['percent' => number_format($data['food_cost_percent'], 1)]) }} · {{ __('analytics.thresholds', ['pop' => $data['thresholds']['popularity'], 'margin' => $money($data['thresholds']['margin'])]) }}</p>@endif

        @if ($data['items'])
            <x-ui.card :title="__('analytics.eng_dishes')" class="mb-5">
                <div class="overflow-x-auto"><table class="w-full text-sm">
                    <thead class="text-start text-muted"><tr><th class="py-2 text-start">{{ __('analytics.dish') }}</th><th class="text-end">{{ __('analytics.sold') }}</th><th class="text-end">{{ __('analytics.unit_margin') }}</th><th class="text-end">{{ __('analytics.margin') }}</th><th class="ps-3 text-start">{{ __('analytics.class') }}</th></tr></thead>
                    <tbody>@foreach ($data['items'] as $i)
                        <tr class="border-t border-line"><td class="py-2">{{ $i['name'] }}<span class="block text-xs text-muted">{{ $i['category'] }}</span></td><td class="tnum text-end">{{ $i['qty'] }}</td><td class="tnum text-end">{{ $money($i['unit_margin']) }}</td><td class="tnum text-end">{{ $money($i['margin']) }}</td>
                            <td class="ps-3"><x-ui.badge :tone="$tone[$i['class']]">{{ __('analytics.class_'.$i['class']) }}</x-ui.badge></td></tr>
                    @endforeach</tbody>
                </table></div>
            </x-ui.card>
        @endif

        @if ($data['unclassified'])
            <x-ui.alert type="info" class="mb-5">{{ trans_choice('analytics.no_cost', count($data['unclassified']), ['count' => count($data['unclassified'])]) }}: {{ collect($data['unclassified'])->pluck('name')->take(8)->implode(', ') }}</x-ui.alert>
        @endif

        <x-ui.card :title="__('analytics.by_category')">
            <ul class="space-y-2 text-sm">@foreach ($data['categories'] as $c)
                <li><div class="flex justify-between"><span>{{ $c['name'] ?: '–' }}</span><span class="tnum text-muted">{{ $money($c['revenue']) }} · {{ $c['share'] }}%</span></div>
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full bg-brand-500" style="width: {{ $c['share'] }}%"></div></div></li>
            @endforeach</ul>
        </x-ui.card>
    @endif
</x-layouts.app>
