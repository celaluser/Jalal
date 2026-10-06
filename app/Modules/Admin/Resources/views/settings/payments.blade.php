<x-layouts.admin :title="__('admin.nav.payments')">
    <p class="mb-4 max-w-3xl text-sm text-gray-500">{{ __('admin.payments.help') }}</p>
    <div class="grid gap-6 xl:grid-cols-2">
        @foreach ($gateways as $code => $gateway)
            <x-ui.card>
                <form method="POST" action="{{ route('admin.settings.payments.update', $code) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold">{{ $gateway->name() }}</h2>
                        @if ($manager->isReady($code))<span class="text-xs font-medium text-green-600">● {{ __('admin.payments.ready') }}</span>
                        @elseif ($manager->isEnabled($code))<span class="text-xs font-medium text-amber-600">● {{ __('admin.payments.incomplete') }}</span>
                        @else<span class="text-xs text-gray-400">○ {{ __('admin.payments.off') }}</span>@endif
                    </div>
                    <x-ui.checkbox name="enabled" :label="__('admin.payments.enable')" :checked="$manager->isEnabled($code)" />
                    @php($config = $manager->config($code))
                    @foreach ($gateway->fields() as $field)
                        @php($label = $field['label'].(! empty($field['optional']) ? '' : ' *'))
                        @if ($field['type'] === 'select')
                            <x-ui.select :name="$field['key']" :label="$label" :options="$field['options']" :value="$config->get($field['key'], array_key_first($field['options']))" />
                        @elseif ($field['type'] === 'textarea')
                            <div>
                                <label for="{{ $code }}-{{ $field['key'] }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
                                <textarea id="{{ $code }}-{{ $field['key'] }}" name="{{ $field['key'] }}" rows="4" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950">{{ $config->get($field['key']) }}</textarea>
                                <p class="mt-1 text-xs text-gray-500">{{ __('admin.payments.reference_hint') }}</p>
                            </div>
                        @elseif ($field['type'] === 'secret')
                            {{-- Stored secrets are never sent back to the browser; blank keeps the saved value. --}}
                            <x-ui.input :name="$field['key']" type="password" :label="$label" autocomplete="off"
                                        :placeholder="$config->get($field['key']) !== null ? '•••••••• '.__('admin.payments.saved') : ''" />
                        @else
                            <x-ui.input :name="$field['key']" :label="$label" :value="$config->get($field['key'])" autocomplete="off" />
                        @endif
                    @endforeach
                    <div>
                        <p class="mb-1 text-xs font-medium text-gray-500">{{ __('admin.payments.webhook_url') }}</p>
                        <code class="block break-all rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-800">{{ route('webhooks.payments', $code) }}</code>
                    </div>
                    <x-ui.button class="!w-auto">{{ __('admin.save') }}</x-ui.button>
                </form>
            </x-ui.card>
        @endforeach
    </div>
</x-layouts.admin>
