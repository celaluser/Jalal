<x-layouts.app :title="__('orders.gateways_title')">
    <x-ui.page-header :title="__('orders.gateways_title')" :description="__('orders.gateways_sub')" />

    @unless ($permitted)
        <x-ui.alert type="warning" class="mb-5"><span class="flex flex-wrap items-center justify-between gap-2"><span>{{ __('orders.gateways_locked') }}</span><a class="font-semibold underline" href="{{ route('billing.index') }}">{{ __('team.upgrade') }}</a></span></x-ui.alert>
    @endunless
    @if ($commission > 0)<x-ui.alert class="mb-5">{{ __('orders.gateways_commission', ['percent' => rtrim(rtrim(number_format($commission, 2), '0'), '.')]) }}</x-ui.alert>@endif
    <p class="mb-5 text-sm text-muted">{{ __('orders.gateways_wallets') }}</p>

    <div class="grid gap-5 xl:grid-cols-2">
        @foreach ($gateways as $code => $gateway)
            @php($ready = $manager->ready($restaurant, $code))
            <form method="POST" action="{{ route('payments.settings.update', $code) }}" class="card" x-data="{ open: {{ $manager->enabled($restaurant, $code) ? 'true' : 'false' }} }">
                @csrf @method('PUT')
                <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-xl bg-surface-2 text-fg"><x-ui.icon name="credit-card" size="5" /></span>
                        <div><h2 class="text-[15px] font-semibold">{{ $gateway->name() }}</h2>
                            @if ($ready)<x-ui.status value="ready" :label="__('admin.payments.ready')" />@elseif ($manager->enabled($restaurant, $code))<x-ui.status value="pending" :label="__('admin.payments.incomplete')" />@else<x-ui.badge>{{ __('admin.payments.off') }}</x-ui.badge>@endif
                            @unless ($gateway->supportsCurrency((string) $restaurant->currency_code))<x-ui.badge tone="warning">{{ __('orders.gateway_no_currency', ['currency' => $restaurant->currency_code]) }}</x-ui.badge>@endunless</div>
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 text-sm font-medium"><input type="checkbox" name="enabled" value="1" class="check" x-model="open" @checked($manager->enabled($restaurant, $code)) @disabled(! $permitted)>{{ __('admin.payments.enable') }}</label>
                </div>
                <div class="space-y-4 p-5" x-show="open" x-cloak>
                    @php($config = $manager->config($restaurant, $code))
                    @foreach ($gateway->fields() as $field)
                        @php($label = $field['label'].(! empty($field['optional']) ? '' : ' *'))
                        @if ($field['type'] === 'select')
                            <x-ui.select :name="$field['key']" :label="$label" :options="$field['options']" :value="$config->get($field['key'], array_key_first($field['options']))" />
                        @elseif ($field['type'] === 'textarea')
                            <div><label for="{{ $code }}-{{ $field['key'] }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>
                                <textarea id="{{ $code }}-{{ $field['key'] }}" name="{{ $field['key'] }}" rows="4" class="field">{{ $config->get($field['key']) }}</textarea></div>
                        @elseif ($field['type'] === 'secret')
                            <x-ui.input :name="$field['key']" type="password" :label="$label" autocomplete="off" :placeholder="$config->get($field['key']) !== null ? '•••••••• '.__('admin.payments.saved') : ''" />
                        @else
                            <x-ui.input :name="$field['key']" :label="$label" :value="$config->get($field['key'])" autocomplete="off" />
                        @endif
                    @endforeach
                    <div>
                        <p class="eyebrow mb-1.5">{{ __('admin.payments.webhook_url') }}</p>
                        <code class="block break-all rounded-lg bg-surface-2 px-3 py-2 font-mono text-xs" dir="ltr">{{ $restaurant->publicUrl('pay/'.$code.'/webhook') }}</code>
                    </div>
                </div>
                <div class="border-t border-line px-5 py-3.5"><x-ui.button :block="false" size="sm" :disabled="! $permitted">{{ __('admin.save') }}</x-ui.button></div>
            </form>
        @endforeach
    </div>
</x-layouts.app>
