<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 12mm 10mm; }
    body { font-family: DejaVu Sans, sans-serif; color: #14161b; margin: 0; }
    table.grid { width: 100%; border-collapse: separate; border-spacing: 5mm 4mm; table-layout: fixed; }
    td.card { width: 50%; height: 68mm; border: 0.4mm solid #cfd4de; border-radius: 4mm; text-align: center; vertical-align: middle; padding: 3mm; }
    .rest { font-size: 11pt; font-weight: bold; margin: 0 0 1mm; }
    .area { font-size: 8pt; color: #5d6577; margin: 0 0 2mm; }
    .table-name { font-size: 20pt; font-weight: bold; margin: 0 0 2mm; }
    img.qr { width: 44mm; height: 44mm; }
    .caption { font-size: 9pt; margin: 2mm 0 0; }
    .bar { height: 1.6mm; background: {{ $brand }}; border-radius: 1mm; margin: 0 20mm 3mm; }
    .page { page-break-after: always; }
    .page:last-child { page-break-after: auto; }
</style>
</head>
<body>
@foreach ($cards as $page)
    <div class="page">
        <table class="grid">
            @foreach ($page as $row)
                <tr>
                    @foreach ($row as $card)
                        <td class="card">
                            <div class="bar"></div>
                            <p class="rest">{{ $restaurant->name }}</p>
                            <p class="table-name">{{ $card['name'] }}</p>
                            <img class="qr" src="data:image/png;base64,{{ $card['png'] }}" alt="">
                            <p class="caption">{{ $caption }}</p>
                        </td>
                    @endforeach
                    @if ($row->count() === 1)<td class="card" style="border: 0;"></td>@endif
                </tr>
            @endforeach
        </table>
    </div>
@endforeach
</body>
</html>
