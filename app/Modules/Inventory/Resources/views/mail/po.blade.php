<div style="font-family:Arial,sans-serif;font-size:15px;color:#111">
    <p>{{ __('inventory.po_greeting', ['name' => $supplier->name]) }}</p>
    <p>{{ __('inventory.po_intro', ['restaurant' => $restaurant->name, 'number' => $number]) }}</p>
    <ul>@foreach ($lines as $l)<li>{{ $l['name'] }} — <strong>{{ rtrim(rtrim(number_format($l['qty'], 3, '.', ''), '0'), '.') }} {{ $l['unit'] }}</strong></li>@endforeach</ul>
    @if ($note)<p>{{ $note }}</p>@endif
    <p>{{ __('inventory.po_outro') }}</p>
    <p>{{ $restaurant->name }}@if ($restaurant->phone)<br>{{ $restaurant->phone }}@endif</p>
</div>
