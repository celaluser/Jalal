@php
    // Card size in mm follows the paper layout; DomPDF needs plain table cells, so everything is millimetres.
    $landscape = $layout['orientation'] === 'landscape';
    $pageW = $landscape ? 297 : 210;
    $pageH = $landscape ? 210 : 297;
    $cardW = floor(($pageW - 20 - ($layout['cols'] - 1) * 5) / $layout['cols']);
    $cardH = floor(($pageH - 24 - ($layout['rows'] - 1) * 4) / $layout['rows']);
    $big = $template === 'poster';
    $small = in_array($template, ['compact', 'sticker'], true);
    $titleSize = $big ? 54 : ($small ? 13 : ($template === 'tent' ? 30 : 20));
    $restSize = $big ? 22 : ($small ? 8 : 11);
    $capSize = $big ? 20 : ($small ? 7 : 9);
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 12mm 10mm; }
    body { font-family: DejaVu Sans, sans-serif; color: #14161b; margin: 0; }
    table.grid { width: 100%; border-collapse: separate; border-spacing: 5mm 4mm; table-layout: fixed; }
    td.card { width: {{ $cardW }}mm; height: {{ $cardH }}mm; text-align: center; vertical-align: middle; padding: 2mm; overflow: hidden;
        @switch($frame)
            @case('border') border: 1.2mm solid {{ $brand }}; border-radius: 5mm; @break
            @case('ribbon') border: 0.4mm solid {{ $brand }}; border-radius: 3mm; @break
            @case('badge') border: 0.4mm solid #cfd4de; border-radius: 4mm; @break
            @default border: 0.4mm solid #cfd4de; border-radius: 4mm;
        @endswitch
    }
    td.empty { border: 0; }
    .rest { font-size: {{ $restSize }}pt; font-weight: bold; margin: 0 0 1mm; }
    .area { font-size: {{ max(6, $restSize - 3) }}pt; color: #5d6577; margin: 0 0 2mm; }
    .table-name { font-size: {{ $titleSize }}pt; font-weight: bold; margin: 0 0 2mm; }
    img.qr { width: {{ $layout['qr'] }}mm; height: {{ $layout['qr'] }}mm; }
    .caption { font-size: {{ $capSize }}pt; margin: 2mm 0 0; }
    .bar { height: 1.6mm; background: {{ $brand }}; border-radius: 1mm; margin: 0 20mm 3mm; }
    .ribbon { background: {{ $brand }}; color: {{ $ink }}; font-size: {{ $capSize + 1 }}pt; font-weight: bold; padding: 2mm; margin: -2mm -2mm 3mm; }
    .badge { background: {{ $brand }}; color: {{ $ink }}; font-size: {{ $capSize + 1 }}pt; font-weight: bold; padding: 2mm; margin: 3mm -2mm -2mm; }
    .page { page-break-after: always; }
    .page:last-child { page-break-after: auto; }
    .fold { font-size: 6pt; color: #9aa1b2; margin-top: 2mm; }
</style>
</head>
<body>
@foreach ($cards as $page)
    <div class="page">
        <table class="grid">
            @foreach ($page as $row)
                <tr>
                    @foreach ($row as $card)
                        {{-- A table tent is folded in the middle: both faces carry the same code. --}}
                        @foreach ($template === 'tent' ? [0, 1] : [0] as $face)
                        <td class="card">
                            @if ($frame === 'ribbon')<div class="ribbon">{{ $caption }}</div>
                            @elseif ($frame === 'none' || $frame === 'border')<div class="bar"></div>@endif
                            <p class="rest">{{ $restaurant->name }}</p>
                            <p class="table-name">{{ $card['name'] }}</p>
                            <img class="qr" src="data:image/png;base64,{{ $card['png'] }}" alt="">
                            @if ($frame === 'badge')<div class="badge">{{ $caption }}</div>
                            @elseif ($frame !== 'ribbon')<p class="caption">{{ $caption }}</p>@endif
                        </td>
                        @endforeach
                    @endforeach
                    @for ($i = $row->count(); $i < ($template === 'tent' ? 1 : $layout['cols']); $i++)<td class="card empty"></td>@endfor
                </tr>
            @endforeach
        </table>
    </div>
@endforeach
</body>
</html>
