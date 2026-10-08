@php
    $money = fn (int $cents) => $restaurant->money($cents / 100);
    $ranges = ['7' => 'range_7', '30' => 'range_30', '90' => 'range_90'];
    $dur = fn (?int $s) => $s === null ? '–' : ($s < 90 ? $s.' s' : round($s / 60).' min');
@endphp
<x-layouts.app :title="__('analytics.ops_title')">
    <x-ui.page-header :title="__('analytics.ops_title')" :description="__('analytics.ops_sub', ['from' => $period['from']->translatedFormat('M j, Y'), 'to' => $period['to']->translatedFormat('M j, Y')])" :back="['url' => route('reports.index'), 'label' => __('analytics.title')]" />
    <div class="mb-5 flex flex-wrap gap-2">@foreach ($ranges as $key => $label)<a href="{{ route('reports.operations', ['range' => $key]) }}" class="chip {{ $period['key'] === (string) $key ? 'chip-active' : '' }}">{{ __('analytics.'.$label) }}</a>@endforeach</div>

    <x-ui.card :title="__('analytics.ops_staff')" :description="__('analytics.ops_staff_help')" class="mb-5">
        @if ($data['staff'] === [])<x-ui.empty icon="users" :title="__('analytics.ops_no_staff')" />@else
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead class="text-muted"><tr><th class="py-2 text-start">{{ __('analytics.ops_person') }}</th><th class="text-end">{{ __('analytics.ops_accepted') }}</th><th class="text-end">{{ __('analytics.ops_ready') }}</th><th class="text-end">{{ __('analytics.ops_completed') }}</th><th class="text-end">{{ __('analytics.ops_cancelled') }}</th><th class="text-end">{{ __('analytics.ops_accept_time') }}</th></tr></thead>
                <tbody>@foreach ($data['staff'] as $p)<tr class="border-t border-line"><td class="py-2 font-medium">{{ $p['name'] }}</td><td class="tnum text-end">{{ $p['accepted'] }}</td><td class="tnum text-end">{{ $p['ready'] }}</td><td class="tnum text-end">{{ $p['completed'] }}</td><td class="tnum text-end">{{ $p['cancelled'] }}</td><td class="tnum text-end">{{ $dur($p['avg_accept_seconds']) }}</td></tr>@endforeach</tbody>
            </table></div>
        @endif
    </x-ui.card>

    <x-ui.card :title="__('analytics.ops_tables')" :description="__('analytics.ops_tables_help')">
        @if ($data['tables'] === [])<x-ui.empty icon="qr" :title="__('analytics.ops_no_tables')" />@else
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead class="text-muted"><tr><th class="py-2 text-start">{{ __('analytics.ops_table') }}</th><th class="text-end">{{ __('analytics.orders') }}</th><th class="text-end">{{ __('analytics.revenue') }}</th><th class="text-end">{{ __('analytics.average') }}</th><th class="text-end">{{ __('analytics.ops_minutes') }}</th></tr></thead>
                <tbody>@foreach ($data['tables'] as $t)<tr class="border-t border-line"><td class="py-2 font-medium">{{ $t['table'] }}</td><td class="tnum text-end">{{ $t['orders'] }}</td><td class="tnum text-end">{{ $money($t['revenue']) }}</td><td class="tnum text-end">{{ $money($t['average']) }}</td><td class="tnum text-end">{{ $t['avg_minutes'] ?? '–' }}</td></tr>@endforeach</tbody>
            </table></div>
        @endif
    </x-ui.card>
</x-layouts.app>
