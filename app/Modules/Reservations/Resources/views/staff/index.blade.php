<x-layouts.app :title="__('reservations.title')">
    <x-ui.page-header :title="__('reservations.title')" :description="__('reservations.subtitle')">
        <x-slot:actions>@can('reservations.manage')<a class="btn btn-secondary" href="{{ route('reservations.settings') }}">{{ __('reservations.settings') }}</a>@endcan</x-slot:actions>
    </x-ui.page-header>
    @error('reservation')<x-ui.alert type="error" class="mb-4">{{ $message }}</x-ui.alert>@enderror
    @unless ($active)<x-ui.alert type="warning" class="mb-4">{{ __('reservations.off_note') }}</x-ui.alert>@endunless

    <nav class="mb-4 flex flex-wrap items-center gap-2">
        <a class="btn btn-secondary btn-sm" href="{{ route('reservations.index', ['date' => $day->subDay()->toDateString()]) }}" aria-label="{{ __('reservations.prev_day') }}">←</a>
        <form method="GET" class="flex items-center gap-2"><input type="date" name="date" value="{{ $day->toDateString() }}" class="field !w-auto !py-1.5" onchange="this.form.submit()" aria-label="{{ __('reservations.date') }}"></form>
        <a class="btn btn-secondary btn-sm" href="{{ route('reservations.index', ['date' => $day->addDay()->toDateString()]) }}" aria-label="{{ __('reservations.next_day') }}">→</a>
        <span class="ms-2 text-sm font-medium">{{ $day->isoFormat('dddd D MMMM') }}</span>
        @if ($pending)<x-ui.badge tone="warning">{{ trans_choice('reservations.pending_count', $pending, ['count' => $pending]) }}</x-ui.badge>@endif
    </nav>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-3 lg:col-span-2">
            @forelse ($items as $r)
                <article class="card p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><p class="display text-xl font-bold">{{ $r->starts_at->setTimezone($tz)->format('H:i') }} <span class="text-base font-medium">· {{ $r->name }} · {{ trans_choice('reservations.people', $r->party_size, ['count' => $r->party_size]) }}</span></p>
                            <p class="text-sm text-muted">{{ collect([$r->phone, $r->email])->filter()->implode(' · ') }}@if ($r->note) · “{{ $r->note }}”@endif</p></div>
                        <x-ui.badge :tone="['pending' => 'warning', 'confirmed' => 'info', 'seated' => 'success', 'completed' => 'neutral', 'cancelled' => 'danger', 'no_show' => 'danger'][$r->status]" dot>{{ __('reservations.status_'.$r->status) }}</x-ui.badge>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('reservations.table', $r->id) }}" class="flex items-center gap-1">@csrf
                            <select name="table_id" class="field !w-auto !py-1.5 text-sm" onchange="this.form.submit()" aria-label="{{ __('reservations.table') }}"><option value="">{{ __('reservations.no_table') }}</option>@foreach ($tables as $t)<option value="{{ $t->id }}" @selected($r->table_id === $t->id)>{{ table_label($t->name) }}@if ($t->seats) ({{ $t->seats }})@endif</option>@endforeach</select></form>
                        @foreach (['pending' => ['confirmed', 'cancelled'], 'confirmed' => ['seated', 'no_show', 'cancelled'], 'seated' => ['completed']][$r->status] ?? [] as $to)
                            <form method="POST" action="{{ route('reservations.status', $r->id) }}">@csrf<input type="hidden" name="status" value="{{ $to }}"><button class="btn btn-sm {{ in_array($to, ['cancelled', 'no_show'], true) ? 'btn-ghost' : 'btn-secondary' }}">{{ __('reservations.action_'.$to) }}</button></form>
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="card"><x-ui.empty icon="clock" :title="__('reservations.empty')" :text="__('reservations.empty_text')" /></div>
            @endforelse
        </div>

        <x-ui.card :title="__('reservations.add')">
            <form method="POST" action="{{ route('reservations.store') }}" class="space-y-3">@csrf
                <x-ui.input name="name" :label="__('orders.name')" required maxlength="80" />
                <div class="grid grid-cols-2 gap-3"><x-ui.input name="date" type="date" :label="__('reservations.date')" :value="$day->toDateString()" required /><x-ui.input name="time" type="time" :label="__('reservations.time')" required /></div>
                <div class="grid grid-cols-2 gap-3"><x-ui.input name="party_size" type="number" min="1" :label="__('reservations.party')" value="2" required /><x-ui.input name="phone" :label="__('orders.phone_label')" maxlength="40" /></div>
                <x-ui.input name="email" type="email" :label="__('orders.email')" maxlength="190" />
                <x-ui.input name="note" :label="__('reservations.note')" maxlength="300" />
                <x-ui.checkbox name="force" :label="__('reservations.force')" />
                @error('time')<p class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                <x-ui.button :block="false">{{ __('reservations.add') }}</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>
