@php
    $t = collect(['just_now', 'minutes_ago', 'table', 'guest', 'cancel_confirm', 'cancel_reason', 'new_order', 'action_accepted', 'action_preparing', 'action_ready', 'action_completed_dine_in', 'action_completed_takeaway', 'action_completed_delivery', 'action_completed_curbside', 'action_completed_room_service', 'type_dine_in', 'type_takeaway', 'type_delivery', 'type_curbside', 'type_room_service'])
        ->mapWithKeys(fn ($k) => [$k => __('orders.'.$k)])->all();
    $t += collect(['scheduled_for', 'vehicle', 'room', 'packaging', 'request_waiter', 'request_bill', 'request_water', 'request_other'])->mapWithKeys(fn ($k) => [$k => __('orders.'.$k)])->all();
    $config['t'] = $t;
    $config['title'] = __('orders.board_title');
    $config['pauseUrl'] = route('orders.pause');
@endphp
@push('head')@vite(['resources/js/orders.js'])@endpush
<x-layouts.app :title="__('orders.board_title')">
    <div x-data="orderBoard(@js($config))" class="-mx-1">
        <x-ui.page-header :title="__('orders.board_title')" :description="__('orders.board_sub')">
            <x-slot:actions>
                <span class="badge" :class="accepting ? 'badge-success' : 'badge-warning'"><span class="size-1.5 rounded-full bg-current"></span><span x-text="accepting ? @js(__('orders.accepting')) : @js(__('orders.paused'))"></span></span>
                @if ($canManage)<button type="button" class="btn btn-secondary btn-sm" x-on:click="togglePause()"><span x-text="accepting ? @js(__('orders.pause')) : @js(__('orders.resume'))"></span></button>@endif
                <a href="{{ route('orders.batch') }}" class="btn btn-secondary btn-sm"><x-ui.icon name="layers" size="4" />{{ __('orders.batch_title') }}</a>
                <button type="button" class="btn btn-secondary btn-sm" x-on:click="toggleSound()" :aria-pressed="sound"><x-ui.icon name="bell" size="4" /><span x-text="sound ? @js(__('orders.sound_on')) : @js(__('orders.sound_off'))"></span></button>
            </x-slot:actions>
        </x-ui.page-header>

        <p x-show="offline" x-cloak class="mb-3 rounded-xl bg-red-50 px-4 py-2 text-sm font-medium text-red-800 dark:bg-red-950/60 dark:text-red-200" role="alert">{{ __('orders.offline') }}</p>
        <p x-show="!accepting" x-cloak class="mb-3 rounded-xl bg-brand-100 px-4 py-2 text-sm font-medium text-brand-900 dark:bg-brand-900/40 dark:text-brand-100" role="status">{{ __('orders.paused_banner') }}</p>

        {{-- Guests asking for the waiter or the bill --}}
        <ul class="mb-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3" x-show="requests.length" x-cloak aria-label="{{ __('orders.requests') }}">
            <template x-for="r in requests" :key="r.id">
                <li class="flex items-center justify-between gap-3 rounded-xl bg-brand-100 px-4 py-2.5 text-brand-900 dark:bg-brand-900/40 dark:text-brand-100">
                    <span class="min-w-0"><span class="block font-semibold" x-text="r.table"></span><span class="block truncate text-sm" x-text="t['request_' + r.kind] + (r.note ? ' · ' + r.note : '')"></span></span>
                    <button type="button" class="btn btn-secondary btn-sm shrink-0" x-on:click="requestDone(r)">{{ __('orders.request_done') }}</button>
                </li>
            </template>
        </ul>

        <div class="grid gap-4 lg:grid-cols-4">
            @foreach ([['newOrders', 'col_new', 'brand'], ['kitchen', 'col_kitchen', 'info'], ['ready', 'col_ready', 'success'], ['done', 'col_done', 'neutral']] as [$list, $label, $tone])
                <section class="min-w-0" aria-label="{{ __('orders.'.$label) }}">
                    <h2 class="mb-2 flex items-center justify-between px-1 text-sm font-semibold">
                        <span class="flex items-center gap-2"><span class="size-2.5 rounded-full {{ ['brand' => 'bg-brand-500', 'info' => 'bg-blue-500', 'success' => 'bg-accent-500', 'neutral' => 'bg-ink-400'][$tone] }}"></span>{{ __('orders.'.$label) }}</span>
                        <span class="tnum rounded-full bg-surface-2 px-2 py-0.5 text-xs text-muted" x-text="{{ $list }}.length"></span>
                    </h2>
                    <div class="space-y-3">
                        <template x-for="o in {{ $list }}" :key="o.id">
                            <article class="card overflow-hidden transition" :class="[fresh.has(o.id) ? 'ring-2 ring-brand-500' : '', ['completed','cancelled'].includes(o.status) ? 'opacity-70' : '']">
                                <div class="flex items-start justify-between gap-2 border-b border-line px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="display text-xl font-bold leading-none" x-text="'#' + o.number"></p>
                                        <p class="mt-1 truncate text-sm text-muted"><span x-text="o.type === 'dine_in' ? '' : t['type_' + o.type] + ' · '"></span><span x-text="where(o)"></span></p>
                                    </div>
                                    <div class="shrink-0 text-end">
                                        <p class="tnum text-sm font-semibold" :class="late(o) ? 'text-red-600 dark:text-red-400' : ''" x-text="ageText(o)"></p>
                                        <p class="mt-0.5 text-xs font-semibold text-red-600 dark:text-red-400" x-show="late(o)">{{ __('orders.late') }}</p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-1.5 px-4 pt-3 text-xs font-semibold" x-show="o.scheduled || o.vehicle || o.room || o.dispatched || o.tab || o.packaging">
                                    <span class="badge badge-info" x-show="o.scheduled"><x-ui.icon name="clock" size="3" /> <span x-text="t.scheduled_for.replace(':time', new Date(o.scheduled).toLocaleString([], { weekday: 'short', hour: '2-digit', minute: '2-digit' }))"></span></span>
                                    <span class="badge badge-neutral" x-show="o.vehicle" x-text="t.vehicle + ': ' + o.vehicle"></span>
                                    <span class="badge badge-neutral" x-show="o.room" x-text="t.room + ' ' + o.room"></span>
                                    <span class="badge badge-warning" x-show="o.dispatched">{{ __('orders.on_the_way') }}</span>
                                    <span class="badge badge-neutral" x-show="o.tab">{{ __('orders.shared_tab') }}</span>
                                    <span class="badge badge-neutral" x-show="o.packaging" x-text="t.packaging + ' ' + o.packaging"></span>
                                </div>
                                <ul class="space-y-1.5 px-4 py-3 text-sm">
                                    <template x-for="(i, n) in o.items" :key="n">
                                        <li><span class="tnum font-bold" x-text="i.qty + '×'"></span> <span class="font-medium" x-text="i.name"></span>
                                            <span class="block text-xs text-muted" x-show="i.options" x-text="i.options"></span>
                                            <span class="block text-xs font-medium text-brand-800 dark:text-brand-200" x-show="i.note" x-text="'“' + i.note + '”'"></span></li>
                                    </template>
                                </ul>
                                <p class="mx-4 mb-3 rounded-lg bg-brand-100 px-3 py-2 text-sm font-medium text-brand-900 dark:bg-brand-900/40 dark:text-brand-100" x-show="o.note"><span class="font-semibold">{{ __('orders.note') }}:</span> <span x-text="o.note"></span></p>
                                <p class="mx-4 mb-3 text-xs text-muted" x-show="o.address || o.phone"><span x-text="o.phone"></span><span x-show="o.address"> · </span><span x-text="o.address"></span></p>
                                <p class="mx-4 mb-3 text-sm italic text-muted" x-show="o.status === 'cancelled' && o.cancel_reason" x-text="o.cancel_reason"></p>
                                <div class="flex items-center justify-between gap-2 border-t border-line bg-surface-2/50 px-4 py-2.5">
                                    <span class="tnum text-sm font-bold" x-text="o.total"></span>
                                    <span class="badge" :class="o.paid ? 'badge-success' : 'badge-neutral'" x-text="o.paid ? @js(__('orders.paid')) : @js(__('orders.unpaid'))"></span>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 px-4 py-3" x-show="o.allowed.length || o.can_dispatch || ({{ $config['canPay'] ? 'true' : 'false' }} && !o.paid && o.status !== 'cancelled')">
                                    <button type="button" class="btn btn-secondary btn-sm" x-show="o.can_dispatch" :disabled="busy === o.id" x-on:click="dispatch(o)">{{ __('orders.dispatch') }}</button>
                                    <button type="button" class="btn btn-primary btn-sm flex-1" x-show="o.forward && o.allowed.includes(o.forward)" :disabled="busy === o.id" x-on:click="move(o, o.forward)" x-text="label(o)"></button>
                                    <template x-if="{{ $config['canPay'] ? 'true' : 'false' }} && !o.paid && o.status !== 'cancelled'">
                                        <span class="flex gap-1"><button type="button" class="btn btn-secondary btn-sm" x-on:click="pay(o, 'cash')">{{ __('orders.pay_cash') }}</button><button type="button" class="btn btn-secondary btn-sm" x-on:click="pay(o, 'card')">{{ __('orders.pay_card') }}</button></span>
                                    </template>
                                </div>
                                <div class="flex items-center justify-between border-t border-line px-4 py-2 text-xs">
                                    <a class="link" :href="@js(url('/orders')) + '/' + o.id">{{ __('orders.details') }}</a>
                                    <span class="flex items-center gap-3">
                                        <a class="text-muted hover:text-fg" :href="@js(url('/orders')) + '/' + o.id + '/ticket'" target="_blank" rel="noopener">{{ __('orders.print') }}</a>
                                        <button type="button" class="font-medium text-red-600 dark:text-red-400" x-show="o.allowed.includes('cancelled')" x-on:click="cancel(o)">{{ __('orders.cancel') }}</button>
                                    </span>
                                </div>
                            </article>
                        </template>
                        <p class="rounded-2xl border border-dashed border-line-strong px-4 py-8 text-center text-sm text-muted" x-show="{{ $list }}.length === 0">{{ __('orders.empty_col') }}</p>
                    </div>
                </section>
            @endforeach
        </div>

        <div x-show="toast" x-cloak x-transition.opacity class="fixed inset-x-0 bottom-6 z-50 flex justify-center px-4" role="status"><span class="btn btn-dark shadow-pop" x-text="toast"></span></div>
    </div>
</x-layouts.app>
