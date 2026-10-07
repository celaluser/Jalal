<x-layouts.admin :title="__('admin.nav.payments')">
    <x-ui.page-header :title="__('admin.nav.payments')" :description="__('admin.payments.help')" />
    <div class="grid gap-5 xl:grid-cols-2">
        @foreach ($gateways as $code => $gateway)
            @php($ready = $manager->isReady($code))
            <form method="POST" action="{{ route('admin.settings.payments.update', $code) }}" class="card" x-data="{ open: {{ $manager->isEnabled($code) ? 'true' : 'false' }} }">
                @csrf @method('PUT')
                <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-xl bg-surface-2 text-fg"><x-ui.icon :name="$code === 'bank_transfer' ? 'wallet' : 'credit-card'" size="5" /></span>
                        <div><h2 class="text-[15px] font-semibold">{{ $gateway->name() }}</h2>
                            @if ($ready)<x-ui.status value="ready" :label="__('admin.payments.ready')" />@elseif ($manager->isEnabled($code))<x-ui.status value="pending" :label="__('admin.payments.incomplete')" />@else<x-ui.badge>{{ __('admin.payments.off') }}</x-ui.badge>@endif</div>
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 text-sm font-medium"><input type="checkbox" name="enabled" value="1" class="check" x-model="open" @checked($manager->isEnabled($code))>{{ __('admin.payments.enable') }}</label>
                </div>
                <div class="space-y-4 p-5" x-show="open" x-cloak>
                    @php($config = $manager->config($code))
                    @foreach ($gateway->fields() as $field)
                        @php($label = $field['label'].(! empty($field['optional']) ? '' : ' *'))
                        @if ($field['type'] === 'select')
                            <x-ui.select :name="$field['key']" :label="$label" :options="$field['options']" :value="$config->get($field['key'], array_key_first($field['options']))" />
                        @elseif ($field['type'] === 'textarea')
                            <div>
                                <label for="{{ $code }}-{{ $field['key'] }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>
                                <textarea id="{{ $code }}-{{ $field['key'] }}" name="{{ $field['key'] }}" rows="4" class="field">{{ $config->get($field['key']) }}</textarea>
                                <p class="mt-1.5 text-xs text-muted">{{ __('admin.payments.reference_hint') }}</p>
                            </div>
                        @elseif ($field['type'] === 'secret')
                            <x-ui.input :name="$field['key']" type="password" :label="$label" autocomplete="off" :placeholder="$config->get($field['key']) !== null ? '•••••••• '.__('admin.payments.saved') : ''" />
                        @else
                            <x-ui.input :name="$field['key']" :label="$label" :value="$config->get($field['key'])" autocomplete="off" />
                        @endif
                    @endforeach
                    <div>
                        <p class="eyebrow mb-1.5">{{ __('admin.payments.webhook_url') }}</p>
                        <code class="block break-all rounded-lg bg-surface-2 px-3 py-2 font-mono text-xs">{{ route('webhooks.payments', $code) }}</code>
                    </div>
                </div>
                <div class="border-t border-line px-5 py-3.5"><x-ui.button :block="false" size="sm">{{ __('admin.save') }}</x-ui.button></div>
            </form>
        @endforeach
    </div>
</x-layouts.admin>
