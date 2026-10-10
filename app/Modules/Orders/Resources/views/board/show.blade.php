@php($money = fn (int $c) => $restaurant->money($c / 100))
<x-layouts.app :title="__('orders.order_number', ['number' => $order->label()])">
    <x-ui.page-header :title="__('orders.order_number', ['number' => $order->label()])" :back="['url' => route('orders.board'), 'label' => __('orders.back')]">
        <x-slot:actions>
            <x-ui.status :value="$order->status" :label="__('orders.status_'.$order->status)" />
            <form method="POST" action="{{ route('orders.print', $order->id) }}" class="inline-flex gap-1">@csrf
                <button name="kind" value="kitchen" class="btn btn-secondary btn-sm"><x-ui.icon name="printer" size="4" />{{ __('orders.print_kitchen') }}</button>
                <button name="kind" value="receipt" class="btn btn-secondary btn-sm">{{ __('orders.print_receipt') }}</button></form>
            <a class="btn btn-secondary btn-sm" href="{{ route('orders.ticket', $order->id) }}" target="_blank" rel="noopener"><x-ui.icon name="receipt" size="4" />{{ __('orders.print') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    @error('order')<x-ui.alert type="error" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <x-ui.card :title="__('orders.items')">
                <ul class="divide-y divide-line">
                    @foreach ($order->items as $item)
                        <li class="flex items-start justify-between gap-4 py-3">
                            <div><p class="font-medium"><span class="tnum font-bold">{{ $item->qty }}×</span> {{ $item->name }}</p>
                                @if ($item->optionsLabel())<p class="text-sm text-muted">{{ $item->optionsLabel() }}</p>@endif
                                @if ($item->note)<p class="text-sm italic text-brand-800 dark:text-brand-200">“{{ $item->note }}”</p>@endif</div>
                            <p class="tnum shrink-0 font-semibold"><bdi>{{ $money($item->total_cents) }}</bdi></p>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-4 space-y-1.5 border-t border-line pt-4 text-sm tnum">
                    <div class="flex justify-between"><dt class="text-muted">{{ __('orders.subtotal') }}</dt><dd><bdi>{{ $money($order->subtotal_cents) }}</bdi></dd></div>
                    @if ($order->discount_cents)<div class="flex justify-between text-accent-700 dark:text-accent-300"><dt>{{ __('marketing.promo_discount') }} · <span class="font-mono" dir="ltr">{{ $order->promo_code }}</span></dt><dd><bdi>−{{ $money($order->discount_cents) }}</bdi></dd></div>@endif
                    @if ($order->manual_discount_cents)<div class="flex justify-between text-accent-700 dark:text-accent-300"><dt>{{ __('orders.discount') }}</dt><dd><bdi>−{{ $money($order->manual_discount_cents) }}</bdi></dd></div>@endif
                    @if ($order->packaging_cents)<div class="flex justify-between"><dt class="text-muted">{{ __('orders.packaging') }}</dt><dd><bdi>{{ $money($order->packaging_cents) }}</bdi></dd></div>@endif
                    @if ($order->service_cents)<div class="flex justify-between"><dt class="text-muted">{{ __('orders.service') }}</dt><dd><bdi>{{ $money($order->service_cents) }}</bdi></dd></div>@endif
                    @if ($order->delivery_cents)<div class="flex justify-between"><dt class="text-muted">{{ __('orders.delivery_fee') }}</dt><dd><bdi>{{ $money($order->delivery_cents) }}</bdi></dd></div>@endif
                    @if ($order->tax_cents)<div class="flex justify-between"><dt class="text-muted">{{ __('orders.tax') }}</dt><dd><bdi>{{ $money($order->tax_cents) }}</bdi></dd></div>@endif
                    <div class="flex justify-between border-t border-line pt-2 text-base font-bold"><dt>{{ __('orders.total') }}</dt><dd><bdi>{{ $money($order->total_cents) }}</bdi></dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('orders.payments')">
                <dl class="mb-3 space-y-1.5 text-sm tnum">
                    <div class="flex justify-between"><dt class="text-muted">{{ __('orders.paid_so_far') }}</dt><dd><bdi>{{ $money($order->paid_cents) }}</bdi></dd></div>
                    @if ($order->tip_cents)<div class="flex justify-between"><dt class="text-muted">{{ __('orders.tip') }}</dt><dd><bdi>{{ $money($order->tip_cents) }}</bdi></dd></div>@endif
                    @if ($order->refunded_cents)<div class="flex justify-between"><dt class="text-muted">{{ __('orders.refunded') }}</dt><dd><bdi>−{{ $money($order->refunded_cents) }}</bdi></dd></div>@endif
                    <div class="flex justify-between font-semibold"><dt>{{ __('orders.still_owed') }}</dt><dd><bdi>{{ $money(max(0, $order->total_cents - $order->paid_cents)) }}</bdi></dd></div>
                </dl>
                @if ($payments->isNotEmpty())
                    <ul class="divide-y divide-line border-t border-line">
                        @foreach ($payments as $p)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-2.5 text-sm">
                                <div><p class="font-medium">{{ __('orders.pay_'.$p->method) }}@if ($p->gateway) · {{ $p->gateway }}@endif
                                        <x-ui.badge :tone="['paid' => 'success', 'pending' => 'warning', 'failed' => 'danger'][$p->status]">{{ __('orders.payment_'.$p->status) }}</x-ui.badge></p>
                                    <p class="text-muted tnum"><bdi>{{ $money($p->amount_cents) }}@if ($p->tip_cents) + {{ __('orders.tip') }} {{ $money($p->tip_cents) }}@endif @if ($p->refunded_cents) · {{ __('orders.refunded') }} {{ $money($p->refunded_cents) }}@endif</bdi> · {{ ($p->paid_at ?? $p->created_at)->toDayDateTimeString() }}@if ($p->user) · {{ $p->user->name }}@endif</p></div>
                                @if ($canPay && $p->refundable() > 0)
                                    <form method="POST" action="{{ route('orders.payments.refund', $p->id) }}" class="flex items-center gap-2" onsubmit="return confirm('{{ __('orders.refund_confirm') }}')">@csrf
                                        <input name="amount" type="number" step="0.01" min="0.01" max="{{ $p->refundable() / 100 }}" class="field !w-24 !py-1.5 text-sm" placeholder="{{ $p->refundable() / 100 }}" aria-label="{{ __('orders.refund_amount') }}" dir="ltr">
                                        <input name="reason" class="field !w-32 !py-1.5 text-sm" maxlength="120" placeholder="{{ __('orders.refund_reason') }}" aria-label="{{ __('orders.refund_reason') }}">
                                        <x-ui.button variant="secondary" size="sm" :block="false">{{ __('orders.refund') }}</x-ui.button></form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($canPay && $order->status !== 'cancelled' && $order->total_cents > $order->paid_cents)
                    <form method="POST" action="{{ route('orders.payments.add', $order->id) }}" class="mt-3 grid gap-2 border-t border-line pt-3 sm:grid-cols-4">@csrf
                        <select name="method" class="field" aria-label="{{ __('orders.pay_how') }}"><option value="cash">{{ __('orders.pay_cash') }}</option><option value="card">{{ __('orders.pay_card') }}</option></select>
                        <input name="amount" type="number" step="0.01" min="0.01" class="field" placeholder="{{ ($order->total_cents - $order->paid_cents) / 100 }}" aria-label="{{ __('orders.pay_amount') }}" dir="ltr">
                        <input name="tip" type="number" step="0.01" min="0" class="field" placeholder="{{ __('orders.tip') }}" aria-label="{{ __('orders.tip') }}" dir="ltr">
                        <x-ui.button :block="false">{{ __('orders.take_payment') }}</x-ui.button>
                    </form>
                    <p class="mt-1.5 text-xs text-muted">{{ __('orders.take_payment_help') }}</p>
                @endif
            </x-ui.card>

            @if (auth()->user()->can('orders.manage') && $order->status !== 'cancelled')
                <x-ui.card :title="__('orders.discount')" :description="__('orders.discount_help')">
                    <form method="POST" action="{{ route('orders.discount', $order->id) }}" class="grid gap-2 sm:grid-cols-4">@csrf
                        <select name="type" class="field" aria-label="{{ __('orders.discount') }}"><option value="percent">%</option><option value="fixed">{{ $restaurant->currency_code }}</option></select>
                        <input name="value" type="number" step="0.01" min="0" class="field" placeholder="{{ $order->manual_discount_cents ? $order->manual_discount_cents / 100 : '10' }}" aria-label="{{ __('orders.discount') }}" dir="ltr" required>
                        <input name="reason" class="field sm:col-span-2" maxlength="120" placeholder="{{ __('orders.discount_reason') }}" aria-label="{{ __('orders.discount_reason') }}">
                        <x-ui.button :block="false" variant="secondary">{{ __('orders.discount_apply') }}</x-ui.button>
                    </form>
                    @if ($order->manual_discount_cents)<p class="mt-2 text-sm text-muted">{{ __('orders.discount_current', ['amount' => $money($order->manual_discount_cents)]) }}</p>@endif
                </x-ui.card>
            @endif

            @if ($order->type === 'dine_in' && $order->table_id && ! $order->isPaid() && $canPay)
                <x-ui.card :title="__('orders.close_table')" :description="__('orders.close_table_help')">
                    <form method="POST" action="{{ route('orders.tables.close', $order->table_id) }}" class="grid gap-2 sm:grid-cols-3">@csrf
                        <select name="method" class="field" aria-label="{{ __('orders.pay_how') }}"><option value="cash">{{ __('orders.pay_cash') }}</option><option value="card">{{ __('orders.pay_card') }}</option></select>
                        <input name="tip" type="number" step="0.01" min="0" class="field" placeholder="{{ __('orders.tip') }}" aria-label="{{ __('orders.tip') }}" dir="ltr">
                        <x-ui.button :block="false" variant="secondary">{{ __('orders.close_table') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif

            <x-ui.card :title="__('orders.history')">
                <ol class="space-y-3 text-sm">
                    @foreach ($order->events as $event)
                        <li class="flex gap-3">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-accent-500"></span>
                            <div>
                                <p class="font-medium">
                                    @if ($event->type === 'placed'){{ __('orders.event_placed') }}
                                    @elseif ($event->type === 'payment'){{ __('orders.event_payment', ['note' => __('orders.pay_'.$event->note)]) }}
                                    @elseif ($event->type === 'discount'){{ __('orders.event_discount', ['note' => $event->note]) }}
                                    @elseif ($event->type === 'refund')@php($rf = explode(' ', explode(' · ', $event->note)[0])){{ __('orders.event_refund', ['note' => $money((int) $rf[0]).' '.__('orders.pay_'.($rf[1] ?? 'cash')).(str_contains($event->note, ' · ') ? ' · '.explode(' · ', $event->note, 2)[1] : '')]) }}
                                    @elseif ($event->type === 'dispatched'){{ __('orders.on_the_way') }}
                                    @else{{ __('orders.status_'.$event->to) }}@endif
                                </p>
                                <p class="text-muted">{{ $event->created_at->toDayDateTimeString() }} · {{ $event->user?->name ?? __('orders.placed_by_guest') }}@if ($event->type === 'status' && $event->note) · {{ $event->note }}@endif</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>
        </div>

        <div class="space-y-5">
            <x-ui.card :title="__('orders.customer')">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('orders.type_'.$order->type) }}</dt><dd class="font-medium">{{ $order->table_name ? table_label($order->table_name) : ($order->customer_name ?: '—') }}</dd></div>
                    @if ($order->table_name && $order->customer_name)<div class="flex justify-between gap-3"><dt class="text-muted">{{ __('orders.guest') }}</dt><dd class="font-medium">{{ $order->customer_name }}</dd></div>@endif
                    @if ($order->customer_email)<div class="flex justify-between gap-3"><dt class="text-muted">{{ __('orders.email') }}</dt><dd class="break-all font-medium" dir="ltr"><a class="link" href="mailto:{{ $order->customer_email }}">{{ $order->customer_email }}</a></dd></div>@endif
                    @if ($order->customer_phone)<div class="flex justify-between gap-3"><dt class="text-muted">{{ __('orders.phone') }}</dt><dd class="font-medium" dir="ltr"><a class="link" href="tel:{{ $order->customer_phone }}">{{ $order->customer_phone }}</a></dd></div>@endif
                    @if ($order->delivery_address)<div><dt class="text-muted">{{ __('orders.address') }}</dt><dd class="mt-0.5 font-medium">{{ $order->delivery_address }}</dd></div>@endif
                    @if ($order->note)<div><dt class="text-muted">{{ __('orders.note') }}</dt><dd class="mt-0.5 font-medium">{{ $order->note }}</dd></div>@endif
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('orders.source_'.$order->source) }}</dt><dd class="tnum text-muted">{{ $order->created_at->toDayDateTimeString() }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ $order->isPaid() ? __('orders.paid') : __('orders.unpaid') }}</dt><dd class="font-medium">{{ $order->payment_method ? __('orders.pay_'.$order->payment_method) : '' }}</dd></div>
                </dl>
            </x-ui.card>

            @if ($order->type === 'delivery' && auth()->user()->canAny(['orders.manage', 'delivery.manage']))
                <x-ui.card :title="__('orders.courier')">
                    <form method="POST" action="{{ route('orders.courier', $order->id) }}" class="flex gap-2">@csrf
                        <select name="courier_id" class="field" aria-label="{{ __('orders.courier_assign') }}"><option value="">{{ __('orders.courier_unassigned') }}</option>
                            @foreach ($couriers as $c)<option value="{{ $c->id }}" @selected($order->courier_id === $c->id)>{{ $c->name }}</option>@endforeach</select>
                        <x-ui.button variant="secondary" :block="false">{{ __('admin.save') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif

            @if ($allowed || $canPay)
                <x-ui.card>
                    <div class="space-y-3">
                        @foreach ($allowed as $to)
                            @if ($to !== 'cancelled')
                                <form method="POST" action="{{ route('orders.status', $order->id) }}">@csrf <input type="hidden" name="status" value="{{ $to }}">
                                    <x-ui.button>{{ $to === 'completed' ? __('orders.action_completed_'.$order->type) : __('orders.action_'.$to) }}</x-ui.button></form>
                            @endif
                        @endforeach
                        @if ($canPay)
                            <div class="flex gap-2">
                                @foreach (['cash', 'card'] as $method)
                                    <form method="POST" action="{{ route('orders.pay', $order->id) }}" class="flex-1">@csrf <input type="hidden" name="method" value="{{ $method }}"><x-ui.button variant="secondary">{{ __('orders.mark_paid') }} · {{ __('orders.pay_'.$method) }}</x-ui.button></form>
                                @endforeach
                            </div>
                        @endif
                        @if (in_array('cancelled', $allowed))
                            <form method="POST" action="{{ route('orders.status', $order->id) }}" onsubmit="return confirm('{{ __('orders.cancel_confirm') }}')" class="space-y-2 border-t border-line pt-3">@csrf <input type="hidden" name="status" value="cancelled">
                                <input name="reason" class="field" maxlength="200" placeholder="{{ __('orders.cancel_reason') }}">
                                <x-ui.button variant="ghost" class="!text-red-600">{{ __('orders.cancel') }}</x-ui.button></form>
                        @endif
                    </div>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.app>
