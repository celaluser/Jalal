<x-layouts.app :title="__('orders.shifts_title')">
    <x-ui.page-header :title="__('orders.shifts_title')" :description="__('orders.shifts_sub')" />
    @error('shift')<x-ui.alert type="error" class="mb-4">{{ $message }}</x-ui.alert>@enderror
    @php($money = fn (int $c) => $restaurant->money($c / 100))

    <div class="max-w-3xl space-y-5">
        @if (! $mine)
            <x-ui.card :title="__('orders.shift_start')" :description="__('orders.shift_start_help')">
                <form method="POST" action="{{ route('shifts.open') }}" class="flex flex-wrap items-end gap-3">@csrf
                    <div class="w-48"><x-ui.input name="opening" type="number" step="0.01" min="0" :label="__('orders.shift_opening')" value="0" required /></div>
                    <x-ui.button :block="false">{{ __('orders.shift_open') }}</x-ui.button>
                </form>
            </x-ui.card>
        @else
            <x-ui.card :title="__('orders.shift_running', ['time' => $mine->opened_at->diffForHumans()])">
                <dl class="space-y-1.5 text-sm tnum">
                    <div class="flex justify-between"><dt class="text-muted">{{ __('orders.shift_opening') }}</dt><dd><bdi>{{ $money($mine->opening_cents) }}</bdi></dd></div>
                    @foreach ($live['methods'] as $method => $row)
                        <div class="flex justify-between"><dt class="text-muted">{{ __('orders.pay_'.$method) }} · {{ $row['count'] }}</dt><dd><bdi>{{ $money($row['amount'] + $row['tips']) }}</bdi></dd></div>
                    @endforeach
                    @if ($live['cash_out'])<div class="flex justify-between"><dt class="text-muted">{{ __('orders.shift_cash_refunds') }}</dt><dd><bdi>−{{ $money($live['cash_out']) }}</bdi></dd></div>@endif
                    <div class="flex justify-between border-t border-line pt-2 text-base font-bold"><dt>{{ __('orders.shift_expected') }}</dt><dd><bdi>{{ $money($live['expected']) }}</bdi></dd></div>
                </dl>
                <form method="POST" action="{{ route('shifts.close', $mine->id) }}" class="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-3">@csrf
                    <x-ui.input name="counted" type="number" step="0.01" min="0" :label="__('orders.shift_counted')" required />
                    <div class="sm:col-span-2"><x-ui.input name="note" :label="__('orders.shift_note')" maxlength="200" /></div>
                    <div class="sm:col-span-3"><x-ui.button :block="false">{{ __('orders.shift_close') }}</x-ui.button></div>
                </form>
            </x-ui.card>
        @endif

        @if ($open->isNotEmpty())
            <x-ui.card :title="__('orders.shifts_open_others')">
                <ul class="divide-y divide-line text-sm">@foreach ($open as $s)<li class="flex justify-between py-2"><span>{{ $s->user->name }}</span><span class="text-muted">{{ $s->opened_at->diffForHumans() }}</span></li>@endforeach</ul>
            </x-ui.card>
        @endif

        <x-ui.card :title="__('orders.shifts_history')">
            @if ($history->isEmpty())
                <p class="text-sm text-muted">{{ __('orders.shifts_none') }}</p>
            @else
                <x-ui.table>
                    <thead><tr><th>{{ __('orders.shift_who') }}</th><th>{{ __('orders.history_when') }}</th><th class="text-end">{{ __('orders.shift_expected') }}</th><th class="text-end">{{ __('orders.shift_counted') }}</th><th class="text-end">{{ __('orders.shift_difference') }}</th></tr></thead>
                    <tbody>
                        @foreach ($history as $s)
                            @php($diff = $s->difference())
                            <tr><td>{{ $s->user->name }}</td><td class="tnum text-muted">{{ $s->opened_at->toDayDateTimeString() }} → {{ $s->closed_at->format('H:i') }}</td>
                                <td class="tnum text-end"><bdi>{{ $money($s->expected_cents) }}</bdi></td><td class="tnum text-end"><bdi>{{ $money($s->closing_cents) }}</bdi></td>
                                <td class="tnum text-end font-semibold {{ $diff < 0 ? 'text-red-600' : ($diff > 0 ? 'text-amber-600' : 'text-accent-700') }}"><bdi>{{ $diff > 0 ? '+' : ($diff < 0 ? '−' : '') }}{{ $money(abs($diff)) }}</bdi></td></tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
