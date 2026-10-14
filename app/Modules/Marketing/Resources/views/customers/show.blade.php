<x-layouts.app :title="$customer->displayName()">
    <x-ui.page-header :title="$customer->name ?: __('marketing.anonymous')" :description="__('marketing.customer_since', ['date' => $customer->created_at->toFormattedDateString()])" :back="['url' => route('customers.index'), 'label' => __('marketing.customers_title')]" />

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <div class="grid gap-3 sm:grid-cols-3">
                <x-ui.stat :label="__('marketing.col_orders')" :value="number_format($customer->orders_count)" />
                <x-ui.stat :label="__('marketing.col_spent')" :value="$restaurant->money($customer->total_cents / 100)" />
                <x-ui.stat :label="__('marketing.avg_order')" :value="$customer->orders_count ? $restaurant->money($customer->total_cents / $customer->orders_count / 100) : '—'" />
            </div>

            <x-ui.card :title="__('marketing.order_history')" :pad="false">
                @if ($orders->isEmpty())
                    <p class="px-6 py-8 text-center text-sm text-muted">{{ __('marketing.no_orders') }}</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($orders as $o)
                            <li>
                                <a href="{{ route('orders.show', $o->id) }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-surface-2 sm:px-6">
                                    <span><span class="font-semibold">#{{ $o->number }}</span> <span class="text-sm text-muted">· {{ __('orders.type_'.$o->type) }} · {{ $o->created_at->toFormattedDateString() }}</span></span>
                                    <span class="flex items-center gap-3"><x-ui.badge>{{ __('orders.status_'.$o->status) }}</x-ui.badge><span class="tnum text-sm font-semibold">{{ $restaurant->money($o->total_cents / 100) }}</span></span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-5">
            <x-ui.card :title="__('marketing.col_customer')">
                <dl class="space-y-3 text-sm">
                    @if ($customer->email)<div><dt class="text-muted">{{ __('orders.email') }}</dt><dd class="break-all font-medium" dir="ltr"><a class="link" href="mailto:{{ $customer->email }}">{{ $customer->email }}</a></dd></div>@endif
                    @if ($customer->phone)<div><dt class="text-muted">{{ __('orders.phone') }}</dt><dd class="font-medium" dir="ltr"><a class="link" href="tel:{{ $customer->phone }}">{{ $customer->phone }}</a></dd></div>@endif
                    <div>
                        <dt class="text-muted">{{ __('marketing.col_marketing') }}</dt>
                        <dd class="font-medium">
                            @if ($customer->unsubscribed_at){{ __('marketing.unsubscribed_on', ['date' => $customer->unsubscribed_at->toFormattedDateString()]) }}
                            @elseif ($customer->marketing_opt_in){{ __('marketing.agreed_on', ['date' => $customer->opted_in_at?->toFormattedDateString()]) }}
                            @else<span class="text-muted">{{ __('marketing.no_consent') }}</span>@endif
                        </dd>
                    </div>
                </dl>
            </x-ui.card>

            @if ($canManage)
                <x-ui.card :title="__('marketing.notes')" :description="__('marketing.notes_help')">
                    <form method="POST" action="{{ route('customers.update', $customer->id) }}" class="space-y-3">
                        @csrf @method('PUT')
                        <textarea name="notes" rows="4" maxlength="2000" class="field" aria-label="{{ __('marketing.notes') }}">{{ old('notes', $customer->notes) }}</textarea>
                        <x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button>
                    </form>
                </x-ui.card>

                <x-ui.card :title="__('marketing.delete_customer')" :description="__('marketing.delete_help')">
                    <form method="POST" action="{{ route('customers.destroy', $customer->id) }}" onsubmit="return confirm('{{ __('marketing.delete_confirm') }}')">
                        @csrf @method('DELETE')
                        <x-ui.button variant="danger" icon="trash" :block="false">{{ __('admin.delete') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @elseif ($customer->notes)
                <x-ui.card :title="__('marketing.notes')"><p class="whitespace-pre-line text-sm">{{ $customer->notes }}</p></x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.app>
