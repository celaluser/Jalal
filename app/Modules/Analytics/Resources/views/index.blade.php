@php
    $money = fn (int $cents) => $restaurant->money($cents / 100);
    $ranges = ['today' => 'range_today', 'yesterday' => 'range_yesterday', '7' => 'range_7', '30' => 'range_30', '90' => 'range_90'];
    $labels = collect($report['daily'])->keys()->map(fn ($d) => \Carbon\Carbon::parse($d)->translatedFormat('M j'))->all();
    $delta = function (?float $d) {
        if ($d === null) { return null; }
        return ($d > 0 ? '▲ +' : ($d < 0 ? '▼ ' : '● ')).number_format($d, 1).'%';
    };
    $days = [__('analytics.mon'), __('analytics.tue'), __('analytics.wed'), __('analytics.thu'), __('analytics.fri'), __('analytics.sat'), __('analytics.sun')];
@endphp
<x-layouts.app :title="__('analytics.title')">
    <x-ui.page-header :title="__('analytics.title')" :description="__('analytics.subtitle', ['from' => $period['from']->translatedFormat('M j, Y'), 'to' => $period['to']->translatedFormat('M j, Y')])">
        <x-slot:actions>
            @if ($full)<a href="{{ route('reports.menu') }}" class="btn btn-secondary"><x-ui.icon name="activity" size="4" />{{ __('analytics.eng_title') }}</a>@endif
            @if ($full)<a href="{{ route('reports.export', request()->only(['range', 'from', 'to'])) }}" class="btn btn-secondary"><x-ui.icon name="download" size="4" />{{ __('analytics.export') }}</a>@endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Range: one row of filters above the charts --}}
    <form method="GET" class="mb-5 flex flex-wrap items-center gap-2" x-data="{ custom: {{ $period['key'] === 'custom' ? 'true' : 'false' }} }">
        @foreach ($ranges as $key => $label)
            @continue(! $full && ! in_array($key, ['today', 'yesterday', '7']))
            <a href="{{ route('reports.index', ['range' => $key]) }}" class="chip {{ $period['key'] === (string) $key ? 'chip-active' : '' }}">{{ __('analytics.'.$label) }}</a>
        @endforeach
        @if ($full)
            <button type="button" class="chip {{ $period['key'] === 'custom' ? 'chip-active' : '' }}" x-on:click="custom = !custom">{{ __('analytics.range_custom') }}</button>
            <span class="flex flex-wrap items-center gap-2" x-show="custom" x-cloak>
                <input type="hidden" name="range" value="custom">
                <input type="date" name="from" value="{{ $period['from']->toDateString() }}" max="{{ now($report['tz'])->toDateString() }}" class="field !w-auto !py-1.5" aria-label="{{ __('analytics.from') }}">
                <span class="text-muted">–</span>
                <input type="date" name="to" value="{{ $period['to']->toDateString() }}" max="{{ now($report['tz'])->toDateString() }}" class="field !w-auto !py-1.5" aria-label="{{ __('analytics.to') }}">
                <button class="btn btn-secondary btn-sm">{{ __('analytics.apply') }}</button>
            </span>
        @endif
    </form>

    @if ($report['orders'] === 0 && $report['cancelled'] === 0)
        <div class="card"><x-ui.empty icon="activity" :title="__('analytics.empty_title')" :text="__('analytics.empty_text')" /></div>
    @else
        {{-- Headline numbers --}}
        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ([['revenue', $money($report['revenue']), 'analytics.revenue'], ['orders', number_format($report['orders']), 'analytics.orders'], ['average', $money($report['average']), 'analytics.average'], ['cancel', number_format($report['cancel_rate'], 1).'%', 'analytics.cancel_rate']] as [$key, $value, $label])
                <div class="card p-4 sm:p-5">
                    <p class="text-sm font-medium text-muted">{{ __($label) }}</p>
                    <p class="display tnum mt-2 text-2xl font-bold sm:text-3xl"><bdi>{{ $value }}</bdi></p>
                    @if ($key !== 'cancel')
                        <p class="tnum mt-1 text-xs text-muted"><bdi>{{ $delta($report['delta'][$key]) ?? '—' }}</bdi> {{ __('analytics.vs_previous') }}</p>
                    @else
                        <p class="tnum mt-1 text-xs text-muted"><bdi>{{ trans_choice('analytics.cancelled_count', $report['cancelled'], ['count' => $report['cancelled']]) }}</bdi></p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="card card-pad mb-5">
            <x-ui.bar-chart :title="__('analytics.revenue_by_day')" :labels="$labels" :values="collect($report['daily'])->pluck('revenue')->map(fn ($c) => round($c / 100, 2))->all()" :unit="$restaurant->currency_code" :decimals="2" />
        </div>

        @if ($full)
            <div class="grid gap-5 lg:grid-cols-2">
                {{-- Best sellers --}}
                <x-ui.card :title="__('analytics.best_sellers')" :description="trans_choice('analytics.items_sold', $report['items_sold'], ['count' => $report['items_sold']])">
                    @php
                        $maxQty = max(1, (int) collect($report['top'])->max('qty'));
                    @endphp
                    <ol class="space-y-3">
                        @forelse ($report['top'] as $i => $row)
                            <li>
                                <div class="flex items-baseline justify-between gap-3 text-sm"><span class="min-w-0 truncate font-medium"><span class="tnum me-2 text-muted">{{ $i + 1 }}</span>{{ $row['name'] }}</span>
                                    <span class="tnum shrink-0 text-muted"><bdi>{{ $row['qty'] }} × · {{ $money($row['revenue']) }}</bdi></span></div>
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full" style="width: {{ round($row['qty'] / $maxQty * 100) }}%; background: var(--viz-series-1)"></div></div>
                            </li>
                        @empty
                            <li class="text-sm text-muted">{{ __('analytics.no_data') }}</li>
                        @endforelse
                    </ol>
                </x-ui.card>

                {{-- Where the orders come from --}}
                <x-ui.card :title="__('analytics.how_they_order')">
                    <div class="space-y-5">
                        @foreach ([['by_type', 'analytics.by_type', 'orders.type_'], ['by_payment', 'analytics.by_payment', 'orders.pay_'], ['by_source', 'analytics.by_source', 'orders.source_']] as [$set, $heading, $prefix])
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">{{ __($heading) }}</p>
                                <ul class="space-y-1.5 text-sm">
                                    @foreach ($report[$set] as $row)
                                        <li class="flex items-center justify-between gap-3"><span>{{ __($prefix.$row['key']) === $prefix.$row['key'] ? $row['key'] : __($prefix.$row['key']) }}</span>
                                            <span class="tnum text-muted"><bdi>{{ $row['orders'] }} · {{ round($row['orders'] / max(1, $report['orders']) * 100) }}% · {{ $money($row['revenue']) }}</bdi></span></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            </div>

            {{-- Busy hours: weekday x hour, one hue, darker = more orders --}}
            @php
                $max = max(1, collect($report['heatmap'])->flatten()->max());
                $peak = null;
                foreach ($report['heatmap'] as $d => $hours) { foreach ($hours as $h => $n) { if ($n > ($peak['n'] ?? 0)) { $peak = ['d' => $d, 'h' => $h, 'n' => $n]; } } }
            @endphp
            <x-ui.card :title="__('analytics.busy_hours')" :description="$peak ? __('analytics.busiest', ['day' => $days[$peak['d']], 'hour' => sprintf('%02d:00', $peak['h']), 'count' => $peak['n']]) : null" class="mt-5">
                <div class="overflow-x-auto" dir="ltr">
                    <div class="grid min-w-[560px] gap-[3px]" style="grid-template-columns: 2.5rem repeat(24, minmax(0, 1fr))" role="table" aria-label="{{ __('analytics.busy_hours') }}">
                        <span></span>
                        @for ($h = 0; $h < 24; $h++)<span class="text-center text-[10px] text-muted tnum">{{ $h % 3 === 0 ? $h : '' }}</span>@endfor
                        @foreach ($report['heatmap'] as $d => $hours)
                            <span class="pe-1 text-end text-xs text-muted">{{ $days[$d] }}</span>
                            @foreach ($hours as $h => $n)
                                <span role="cell" class="aspect-square rounded-[4px] border border-line" title="{{ $days[$d] }} {{ sprintf('%02d:00', $h) }}: {{ trans_choice('analytics.cell_orders', $n, ['count' => $n]) }}" aria-label="{{ $days[$d] }} {{ sprintf('%02d:00', $h) }}: {{ $n }}"
                                      style="background: {{ $n ? 'color-mix(in srgb, var(--viz-series-1) '.max(14, round($n / $max * 100)).'%, transparent)' : 'transparent' }}"></span>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </x-ui.card>

            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.stat :label="__('analytics.new_customers')" :value="number_format($report['customers']['new'])" icon="users" />
                <x-ui.stat :label="__('analytics.returning_customers')" :value="number_format($report['customers']['returning'])" icon="refresh" />
                <x-ui.stat :label="__('analytics.discounts')" :value="$money($report['discounts'])" icon="tag" />
                <x-ui.stat :label="__('analytics.ready_time')" :value="$report['ready_minutes'] !== null ? __('analytics.minutes', ['count' => $report['ready_minutes']]) : '—'" icon="zap" :hint="__('analytics.ready_time_hint')" />
            </div>
        @else
            <div class="card"><x-ui.empty icon="activity" :title="__('analytics.locked_title')" :text="__('analytics.locked_text')"><a href="{{ route('billing.index') }}" class="btn btn-primary">{{ __('analytics.upgrade') }}</a></x-ui.empty></div>
        @endif
    @endif
    @if ($full && app(\App\Modules\Ai\Services\AiManager::class)->configured())
        <x-ui.card :title="__('ai.insights_title')" class="mt-5" x-data="{ tips: [], busy: false, error: '', async go() { this.busy = true; this.error = ''; try { const r = await fetch(@js(route('ai.insights', request()->query())), { headers: { Accept: 'application/json' } }); const d = await r.json().catch(() => ({})); if (r.ok) { this.tips = d.tips; } else { this.error = d.message || ''; } } catch (e) { this.error = @js(__('ai.assistant_error')); } this.busy = false; } }">
            <button type="button" class="btn btn-secondary btn-sm" x-on:click="go()" :disabled="busy"><x-ui.icon name="sparkles" size="4" />{{ __('ai.insights_button') }} · {{ __('ai.credits_cost', ['count' => app(\App\Modules\Ai\Services\AiCredits::class)->cost('insights')]) }}</button>
            <ul class="mt-3 list-inside list-disc space-y-1.5 text-sm" x-show="tips.length" x-cloak><template x-for="t in tips" :key="t"><li x-text="t"></li></template></ul>
            <p class="mt-2 text-xs text-muted" x-show="tips.length" x-cloak>{{ __('ai.insights_note') }}</p>
            <p class="mt-2 text-sm text-red-600" x-show="error" x-text="error" role="alert"></p>
        </x-ui.card>
    @endif
</x-layouts.app>
