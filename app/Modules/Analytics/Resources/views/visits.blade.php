@php
    $ranges = ['7' => 'range_7', '30' => 'range_30', '90' => 'range_90'];
    $maxHour = max(1, max($data['hours']));
    $maxDay = max(1, collect($data['daily'])->max(fn ($d) => $d['views'] + $d['scans']));
@endphp
<x-layouts.app :title="__('analytics.visits_title')">
    <x-ui.page-header :title="__('analytics.visits_title')" :description="__('analytics.visits_sub', ['from' => $period['from']->translatedFormat('M j, Y'), 'to' => $period['to']->translatedFormat('M j, Y')])" :back="['url' => route('reports.index'), 'label' => __('analytics.title')]" />
    <div class="mb-5 flex flex-wrap gap-2">@foreach ($ranges as $key => $label)<a href="{{ route('reports.visits', ['range' => $key]) }}" class="chip {{ $period['key'] === (string) $key ? 'chip-active' : '' }}">{{ __('analytics.'.$label) }}</a>@endforeach</div>

    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat :label="__('analytics.visits_scans')" :value="number_format($data['scans'])" icon="qr" />
        <x-ui.stat :label="__('analytics.visits_views')" :value="number_format($data['views'])" icon="eye" />
        <x-ui.stat :label="__('analytics.visits_orders')" :value="number_format($orders)" icon="receipt" />
        <x-ui.stat :label="__('analytics.visits_conversion')" :value="$data['views'] > 0 ? number_format($orders / $data['views'] * 100, 1) : '–'" icon="activity" />
    </div>

    @if ($data['scans'] + $data['views'] === 0)
        <div class="card"><x-ui.empty icon="qr" :title="__('analytics.visits_none')" /></div>
    @else
        <div class="grid gap-5 lg:grid-cols-2">
            <x-ui.card :title="__('analytics.visits_by_day')">
                <ul class="space-y-1.5 text-sm">@foreach ($data['daily'] as $day => $d)
                    <li class="flex items-center gap-3"><span class="tnum w-14 shrink-0 text-muted">{{ \Carbon\Carbon::parse($day)->translatedFormat('M j') }}</span>
                        <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full bg-brand-500" style="width: {{ round(($d['views'] + $d['scans']) / $maxDay * 100) }}%"></div></div>
                        <span class="tnum w-20 shrink-0 text-end text-muted">{{ $d['scans'] }} / {{ $d['views'] }}</span></li>
                @endforeach</ul>
                <p class="mt-3 text-xs text-muted">{{ __('analytics.visits_scans') }} / {{ __('analytics.visits_views') }}</p>
            </x-ui.card>
            <div class="space-y-5">
                <x-ui.card :title="__('analytics.visits_by_hour')">
                    <div class="flex h-28 items-end gap-1" role="img" aria-label="{{ __('analytics.visits_by_hour') }}">@foreach ($data['hours'] as $h => $n)
                        <div class="flex-1 rounded-t bg-brand-500/80" style="height: {{ max(2, round($n / $maxHour * 100)) }}%" title="{{ sprintf('%02d:00', $h) }} · {{ $n }}"></div>
                    @endforeach</div>
                    <div class="mt-1 flex justify-between text-xs text-muted tnum"><span>00</span><span>06</span><span>12</span><span>18</span><span>23</span></div>
                </x-ui.card>
                @if ($data['tables'])
                    <x-ui.card :title="__('analytics.visits_by_table')">
                        <ul class="divide-y divide-line text-sm">@foreach (array_slice($data['tables'], 0, 10) as $t)<li class="flex justify-between py-2"><span>{{ table_label($tables[$t['table_id']] ?? '#'.$t['table_id']) }}</span><span class="tnum text-muted">{{ $t['scans'] }}</span></li>@endforeach</ul>
                    </x-ui.card>
                @endif
            </div>
        </div>
    @endif
    <p class="mt-5 text-xs text-muted">{{ __('analytics.visits_note') }}</p>
</x-layouts.app>
