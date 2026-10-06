@props(['labels', 'values', 'title', 'unit' => '', 'decimals' => 0, 'integer' => false])
@php
    use App\Modules\Admin\Support\ChartScale;

    $count = max(count($values), 1);
    $scale = ChartScale::nice((float) max($values ?: [0]), 4, (bool) $integer);
    $w = 520; $h = 220; $left = 40; $right = 8; $top = 12; $bottom = 26;
    $plotW = $w - $left - $right; $plotH = $h - $top - $bottom;
    $slot = $plotW / $count;
    $barW = min(24, $slot - 2);                       // thin marks: never fill the slot
    $y = fn ($v) => $top + $plotH - ($v / $scale['max']) * $plotH;
    $fmt = fn ($v) => number_format($v, $decimals).($unit ? ' '.$unit : '');
    $id = 'chart-'.\Illuminate\Support\Str::random(6);
    // Label the first, middle and last category only: a label on every bar is noise.
    $labelAt = array_unique([0, intdiv($count - 1, 2), $count - 1]);
@endphp
<figure class="viz-chart" x-data="{ i: null }" aria-labelledby="{{ $id }}-t">
    <figcaption id="{{ $id }}-t" class="mb-2 text-sm font-semibold">{{ $title }}</figcaption>
    <div class="relative">
        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="h-auto w-full" role="img" aria-label="{{ $title }}">
            @foreach ($scale['ticks'] as $tick)
                <line x1="{{ $left }}" x2="{{ $w - $right }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" stroke="var(--viz-grid)" stroke-width="1"/>
                <text x="{{ $left - 6 }}" y="{{ $y($tick) + 4 }}" text-anchor="end" font-size="13" fill="var(--viz-muted)" style="font-variant-numeric: tabular-nums">{{ number_format($tick) }}</text>
            @endforeach
            @foreach ($values as $n => $value)
                @php
                    $x = $left + $slot * $n + ($slot - $barW) / 2;
                    $barH = max(0, $plotH - ($y($value) - $top));
                    $r = min(4, $barH, $barW / 2);
                @endphp
                @if ($barH > 0)
                    {{-- 4px rounded data end, square at the baseline --}}
                    <path d="M{{ $x }},{{ $top + $plotH }} V{{ $y($value) + $r }} Q{{ $x }},{{ $y($value) }} {{ $x + $r }},{{ $y($value) }} H{{ $x + $barW - $r }} Q{{ $x + $barW }},{{ $y($value) }} {{ $x + $barW }},{{ $y($value) + $r }} V{{ $top + $plotH }} Z"
                          fill="var(--viz-series-1)" :opacity="i === null || i === {{ $n }} ? 1 : 0.55"/>
                @endif
                {{-- Hit target is the whole slot, much larger than the mark --}}
                <rect x="{{ $left + $slot * $n }}" y="{{ $top }}" width="{{ $slot }}" height="{{ $plotH }}" fill="transparent"
                      x-on:mouseenter="i = {{ $n }}" x-on:mouseleave="i = null" x-on:focus="i = {{ $n }}" x-on:blur="i = null" tabindex="0"
                      aria-label="{{ $labels[$n] }}: {{ $fmt($value) }}"/>
            @endforeach
            @foreach ($labelAt as $n)
                <text x="{{ $left + $slot * $n + $slot / 2 }}" y="{{ $h - 8 }}" text-anchor="{{ $n === 0 ? 'start' : ($n === $count - 1 ? 'end' : 'middle') }}"
                      font-size="13" fill="var(--viz-muted)">{{ $labels[$n] }}</text>
            @endforeach
        </svg>
        @foreach ($values as $n => $value)
            <div x-show="i === {{ $n }}" x-cloak role="status"
                 class="pointer-events-none absolute -top-1 rounded-md bg-gray-900 px-2 py-1 text-xs text-white shadow dark:bg-gray-100 dark:text-gray-900"
                 style="left: {{ round(($left + $slot * $n + $slot / 2) / $w * 100, 2) }}%; transform: translateX(-50%)">
                <span class="opacity-80">{{ $labels[$n] }}</span> · <strong>{{ $fmt($value) }}</strong>
            </div>
        @endforeach
    </div>
    <details class="mt-2 text-xs text-gray-500">
        <summary class="cursor-pointer">{{ __('admin.chart.table_view') }}</summary>
        <table class="mt-1 w-full">
            <tbody>
            @foreach ($values as $n => $value)
                <tr class="border-t border-gray-100 dark:border-gray-800"><td class="py-0.5">{{ $labels[$n] }}</td><td class="text-right" style="font-variant-numeric: tabular-nums">{{ $fmt($value) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </details>
</figure>
