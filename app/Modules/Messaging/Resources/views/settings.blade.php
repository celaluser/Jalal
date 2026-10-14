<x-layouts.admin :title="__('admin.nav.messaging')">
    <x-ui.page-header :title="__('admin.nav.messaging')" :description="__('messaging.help')" />

    <form method="POST" action="{{ route('admin.messaging.choose') }}" class="card card-pad mb-5 grid gap-4 sm:grid-cols-3">
        @csrf @method('PUT')
        @foreach (['sms', 'whatsapp'] as $channel)
            <div>
                <label for="{{ $channel }}_provider" class="mb-1.5 block text-sm font-medium">{{ __('messaging.use_for_'.$channel) }}</label>
                <select id="{{ $channel }}_provider" name="{{ $channel }}_provider" class="field">
                    <option value="">{{ __('messaging.none') }}</option>
                    @foreach ($manager->all() as $code => $p)@if (in_array($channel, $p->channels(), true))<option value="{{ $code }}" @selected(platform_setting('messaging.'.$channel.'_provider') === $code)>{{ $p->name() }}{{ $manager->isConfigured($p, $channel) ? '' : ' ('.__('messaging.incomplete').')' }}</option>@endif @endforeach
                </select>
            </div>
        @endforeach
        <div class="flex items-end"><x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button></div>
    </form>

    <div class="grid gap-5 xl:grid-cols-2">
        @foreach ($manager->all() as $code => $provider)
            @php($config = $manager->config($provider))
            <form method="POST" action="{{ route('admin.messaging.update', $code) }}" class="card">
                @csrf @method('PUT')
                <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <h2 class="text-[15px] font-semibold">{{ $provider->name() }}</h2>
                    <div class="flex gap-1.5">@foreach ($provider->channels() as $ch)<x-ui.badge>{{ strtoupper($ch) }}</x-ui.badge>@endforeach @if ($manager->isConfigured($provider))<x-ui.status value="ready" :label="__('messaging.ready')" />@endif</div>
                </div>
                <div class="space-y-4 p-5">
                    @foreach ($provider->fields() as $field)
                        @php($label = $field['label'].(! empty($field['optional']) ? '' : ' *'))
                        @if ($field['type'] === 'select')
                            <x-ui.select :name="$field['key']" :label="$label" :options="$field['options']" :value="$config[$field['key']] ?? array_key_first($field['options'])" />
                        @elseif ($field['type'] === 'secret')
                            <x-ui.input :name="$field['key']" type="password" :label="$label" autocomplete="off" :placeholder="$config[$field['key']] ? '•••••••• '.__('messaging.saved') : ''" />
                        @else
                            <x-ui.input :name="$field['key']" :label="$label" :value="$config[$field['key']] ?? ''" autocomplete="off" />
                        @endif
                    @endforeach
                </div>
                <div class="border-t border-line px-5 py-3.5"><x-ui.button :block="false" size="sm">{{ __('admin.save') }}</x-ui.button></div>
            </form>
        @endforeach
    </div>

    <x-ui.card :title="__('messaging.test_title')" :description="__('messaging.test_help')" class="mt-5">
        <form method="POST" action="{{ route('admin.messaging.test') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <x-ui.select name="channel" :label="__('messaging.channel')" :options="['sms' => 'SMS', 'whatsapp' => 'WhatsApp']" value="sms" />
            <div class="min-w-56 flex-1"><x-ui.input name="to" :label="__('messaging.to')" placeholder="+905321112233" required dir="ltr" /></div>
            <x-ui.button :block="false">{{ __('messaging.send_test') }}</x-ui.button>
        </form>
    </x-ui.card>
</x-layouts.admin>
