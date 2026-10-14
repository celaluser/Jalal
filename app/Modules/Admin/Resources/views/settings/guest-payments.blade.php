<x-layouts.admin :title="__('admin.nav.guest_payments')">
    <x-ui.page-header :title="__('admin.nav.guest_payments')" :description="__('admin.guest_payments.help')" />
    <form method="POST" action="{{ route('admin.settings.guest-payments.update') }}" class="max-w-3xl space-y-5">
        @csrf @method('PUT')
        <x-ui.card>
            <div class="space-y-4">
                <x-ui.checkbox name="enabled" :label="__('admin.guest_payments.enabled')" :checked="$manager->platformEnabled()" />
                <x-ui.input name="commission_percent" type="number" step="0.01" min="0" max="50" inputmode="decimal" :label="__('admin.guest_payments.commission')" :value="$manager->commissionPercent()" :hint="__('admin.guest_payments.commission_hint')" />
            </div>
        </x-ui.card>
        <x-ui.card :title="__('admin.guest_payments.allowed')" :description="__('admin.guest_payments.allowed_hint')">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($gateways as $code => $g)
                    <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="allowed[]" value="{{ $code }}" class="check" @checked(platform_setting('guest_payments.allowed.'.$code, '1') !== '0')>{{ $g->name() }}</label>
                @endforeach
            </div>
        </x-ui.card>
        <div class="flex justify-end"><x-ui.button :block="false" size="lg">{{ __('admin.save') }}</x-ui.button></div>
    </form>

    <x-ui.card :title="__('admin.guest_payments.volume')" class="mt-6">
        @if ($stats->isEmpty())
            <p class="text-sm text-muted">{{ __('admin.guest_payments.none') }}</p>
        @else
            <x-ui.table>
                <thead><tr><th>{{ __('admin.guest_payments.restaurant') }}</th><th>{{ __('admin.guest_payments.payments') }}</th><th>{{ __('admin.guest_payments.volume_col') }}</th><th>{{ __('admin.guest_payments.commission_col') }}</th><th>{{ __('admin.guest_payments.unbilled') }}</th></tr></thead>
                <tbody>
                    @foreach ($stats as $row)
                        @php($r = $restaurants[$row->restaurant_id] ?? null)
                        <tr><td>{{ $r?->name ?? '#'.$row->restaurant_id }}</td><td class="tnum">{{ $row->payments }}</td>
                            <td class="tnum"><bdi>{{ $r ? $r->money($row->volume / 100) : number_format($row->volume / 100, 2) }}</bdi></td>
                            <td class="tnum"><bdi>{{ $r ? $r->money($row->commission / 100) : number_format($row->commission / 100, 2) }}</bdi></td>
                            <td class="tnum font-semibold"><bdi>{{ $r ? $r->money($row->unbilled / 100) : number_format($row->unbilled / 100, 2) }}</bdi></td></tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>
</x-layouts.admin>
