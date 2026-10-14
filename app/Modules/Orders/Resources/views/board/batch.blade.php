<x-layouts.app :title="__('orders.batch_title')">
    <x-ui.page-header :title="__('orders.batch_title')" :description="__('orders.batch_sub')" :back="['url' => route('orders.board'), 'label' => __('orders.board_title')]" />

    @if ($stations)
        <nav class="mb-4 flex flex-wrap gap-2" aria-label="{{ __('orders.station') }}">
            <a href="{{ route('orders.batch') }}" class="btn btn-sm {{ $station ? 'btn-secondary' : 'btn-primary' }}">{{ __('orders.all_stations') }}</a>
            @foreach ($stations as $s)<a href="{{ route('orders.batch', ['station' => $s]) }}" class="btn btn-sm {{ $station === $s ? 'btn-primary' : 'btn-secondary' }}">{{ $s }}</a>@endforeach
        </nav>
    @endif

    <p class="mb-3 text-sm text-muted">{{ trans_choice('orders.batch_summary', $orderCount, ['count' => $orderCount]) }}</p>
    @if ($rows->isEmpty())
        <div class="card"><x-ui.empty icon="check" :title="__('orders.batch_empty')" :text="__('orders.batch_empty_text')" /></div>
    @else
        <ul class="grid gap-2" x-data="{ timer: setTimeout(() => location.reload(), 20000) }">
            @foreach ($rows as $row)
                <li class="card flex items-center gap-4 p-4">
                    <span class="display tnum grid size-14 shrink-0 place-items-center rounded-2xl bg-brand-100 text-2xl font-extrabold text-brand-900 dark:bg-brand-900/40 dark:text-brand-100">{{ $row['qty'] }}×</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-lg font-semibold">{{ $row['name'] }}@if ($row['station'])<x-ui.badge class="ms-2">{{ $row['station'] }}</x-ui.badge>@endif</p>
                        @if ($row['options'])<p class="text-sm text-muted">{{ $row['options'] }}</p>@endif
                        @if ($row['note'])<p class="text-sm font-medium text-brand-800 dark:text-brand-200">“{{ $row['note'] }}”</p>@endif
                    </div>
                    <p class="tnum shrink-0 text-end text-sm text-muted"><bdi>{{ collect($row['numbers'])->map(fn ($n) => '#'.$n)->implode(' ') }}</bdi></p>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
