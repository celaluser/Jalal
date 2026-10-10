<x-layouts.app :title="__('reservations.settings')">
    <x-ui.page-header :title="__('reservations.settings')" :description="__('reservations.settings_sub')" :back="['url' => route('reservations.index'), 'label' => __('reservations.title')]" />
    @unless ($allowed)<x-ui.alert type="warning" class="mb-4"><span class="flex flex-wrap items-center justify-between gap-2"><span>{{ __('reservations.locked') }}</span><a class="font-semibold underline" href="{{ route('billing.index') }}">{{ __('team.upgrade') }}</a></span></x-ui.alert>@endunless
    <form method="POST" action="{{ route('reservations.settings.update') }}" class="max-w-2xl space-y-5">@csrf @method('PUT')
        <x-ui.card>
            <div class="space-y-4">
                <x-ui.checkbox name="enabled" :label="__('reservations.enabled')" :checked="$s['enabled']" />
                <x-ui.checkbox name="auto_confirm" :label="__('reservations.auto_confirm')" :checked="$s['auto_confirm']" />
                <p class="text-sm text-muted">{{ __('reservations.page_link') }}: <code dir="ltr" class="break-all">{{ $url }}</code></p>
            </div>
        </x-ui.card>
        <x-ui.card :title="__('reservations.hours')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="open_from" type="time" :label="__('reservations.open_from')" :value="$s['open_from']" required /><x-ui.input name="open_to" type="time" :label="__('reservations.open_to')" :value="$s['open_to']" required />
            </div>
            <div class="mt-4 flex flex-wrap gap-2">@foreach ([1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'] as $n => $d)
                <label class="cursor-pointer"><input type="checkbox" name="days[]" value="{{ $n }}" class="peer sr-only" @checked(in_array($n, $s['days']))><span class="inline-flex min-w-11 justify-center rounded-lg border border-line-strong px-2.5 py-1.5 text-sm peer-checked:border-accent-600 peer-checked:bg-accent-50 peer-checked:font-medium dark:peer-checked:bg-accent-900/20">{{ __('analytics.'.$d) }}</span></label>@endforeach</div>
            <p class="mt-1 text-xs text-muted">{{ __('reservations.days_help') }}</p>
        </x-ui.card>
        <x-ui.card :title="__('reservations.rules')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select name="slot_minutes" :label="__('reservations.slot_minutes')" :options="[15 => '15', 30 => '30', 60 => '60']" :value="$s['slot_minutes']" />
                <x-ui.input name="duration_minutes" type="number" min="30" max="480" :label="__('reservations.duration')" :value="$s['duration_minutes']" required />
                <x-ui.input name="max_party" type="number" min="1" max="100" :label="__('reservations.max_party')" :value="$s['max_party']" required />
                <x-ui.input name="lead_minutes" type="number" min="0" :label="__('reservations.lead')" :value="$s['lead_minutes']" required />
                <x-ui.input name="days_ahead" type="number" min="1" max="365" :label="__('reservations.days_ahead')" :value="$s['days_ahead']" required />
                <x-ui.input name="max_covers" type="number" min="1" :label="__('reservations.max_covers')" :value="$s['max_covers']" :hint="__('reservations.max_covers_hint')" required />
 <x-ui.input name="deposit_per_person" type="number" step="0.01" min="0" :label="__('reservations.deposit_setting')" :value="$s['deposit_per_person']" :hint="__('reservations.deposit_setting_hint')" />
                <x-ui.input name="deposit_hold_minutes" type="number" min="5" max="120" :label="__('reservations.deposit_hold')" :value="$s['deposit_hold_minutes']" />
                <x-ui.input name="deposit_refund_hours" type="number" min="0" max="720" :label="__('reservations.deposit_refund')" :value="$s['deposit_refund_hours']" :hint="__('reservations.deposit_refund_hint')" />
                <x-ui.input name="remind_hours" type="number" min="0" max="48" :label="__('reservations.remind')" :value="$s['remind_hours']" :hint="__('reservations.remind_hint')" required />
            </div>
        </x-ui.card>
        <div class="flex justify-end"><x-ui.button :block="false" size="lg">{{ __('admin.save') }}</x-ui.button></div>
    </form>
</x-layouts.app>
