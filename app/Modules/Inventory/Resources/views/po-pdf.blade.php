<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#111}
h1{font-size:20px;margin:0 0 4px}
table{width:100%;border-collapse:collapse;margin-top:14px}
th,td{border-bottom:1px solid #ccc;padding:6px 4px;text-align:left}
th{background:#f3f3f3}
.r{text-align:right}
.muted{color:#666}
</style></head><body>
<h1>{{ __('inventory.po_title') }} {{ $number }}</h1>
<p class="muted">{{ $restaurant->name }}@if ($restaurant->address) · {{ $restaurant->address }}@endif @if ($restaurant->phone) · {{ $restaurant->phone }}@endif<br>{{ $date }}</p>
<p><strong>{{ __('inventory.po_to') }}:</strong> {{ $supplier?->name ?? __('inventory.no_supplier') }}@if ($supplier?->email) · {{ $supplier->email }}@endif @if ($supplier?->phone) · {{ $supplier->phone }}@endif</p>
<table>
<thead><tr><th>#</th><th>{{ __('inventory.name') }}</th><th class="r">{{ __('inventory.quantity') }}</th><th>{{ __('inventory.unit') }}</th><th class="r">{{ __('inventory.po_estimate') }}</th></tr></thead>
<tbody>
@foreach ($lines as $l)
<tr><td>{{ $loop->iteration }}</td><td>{{ $l['name'] }}</td><td class="r">{{ rtrim(rtrim(number_format($l['qty'], 3, '.', ''), '0'), '.') }}</td><td>{{ $l['unit'] }}</td><td class="r">{{ $l['est'] !== null ? $restaurant->money($l['est']) : '—' }}</td></tr>
@endforeach
</tbody>
@if ($total > 0)<tfoot><tr><th colspan="4" class="r">{{ __('inventory.po_total') }}</th><th class="r">{{ $restaurant->money($total) }}</th></tr></tfoot>@endif
</table>
@if ($note)<p>{{ $note }}</p>@endif
</body></html>
