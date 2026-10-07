@php($money = fn (int $c) => $restaurant->money($c / 100))
<x-layouts.app :title="__('orders.order_number', ['number' => $order->label()])">
    <x-ui.page-header :title="__('orders.order_number', ['number' => $order->label()])" :back="['url' => route('orders.board'), 'label' => __('orders.back')]">
        <x-slot:actions>
            <x-ui.status :value="$order->status" :label="__('orders.status_'.$order->status)" />
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
                    @if ($order->service_cents)<div class="flex justify-between"><dt class="text-muted">{{ __('orders.service') }}</dt><dd><bdi>{{ $money($order->service_cents) }}</bdi></dd></div>@endif
                    @if ($order->delivery_cents)<div class="flex justify-between"><dt class="text-muted">{{ __('orders.delivery_fee') }}</dt><dd><bdi>{{ $money($order->delivery_cents) }}</bdi></dd></div>@endif
                    @if ($order->tax_cents)<div class="flex justify-between"><dt class="text-muted">{{ __('orders.tax') }}</dt><dd><bdi>{{ $money($order->tax_cents) }}</bdi></dd></div>@endif
                    <div class="flex justify-between border-t border-line pt-2 text-base font-bold"><dt>{{ __('orders.total') }}</dt><dd><bdi>{{ $money($order->total_cents) }}</bdi></dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('orders.history')">
                <ol class="space-y-3 text-sm">
                    @foreach ($order->events as $event)
                        <li class="flex gap-3">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-accent-500"></span>
                            <div>
                                <p class="font-medium">
                                    @if ($event->type === 'placed'){{ __('orders.event_placed') }}
                                    @elseif ($event->type === 'payment'){{ __('orders.event_payment', ['note' => __('orders.pay_'.$event->note)]) }}
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
