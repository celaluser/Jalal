<x-layouts.app :title="__('affiliate.title')">
    <x-ui.page-header :title="__('affiliate.title')" :description="__('affiliate.subtitle', ['percent' => rtrim(rtrim(number_format($percent, 2), '0'), '.')])" />

    <div class="mb-5 grid gap-3 sm:grid-cols-3">
        <x-ui.stat :label="__('affiliate.invited')" :value="$referrals->count()" icon="users" />
        <x-ui.stat :label="__('affiliate.paying')" :value="$referrals->where('status', 'rewarded')->count()" icon="check-circle" />
        <x-ui.stat :label="__('affiliate.credit')" :value="number_format((float) $restaurant->billing_credit, 2).' '.$currency" icon="wallet" :hint="__('affiliate.credit_hint')" />
    </div>

    <x-ui.card :title="__('affiliate.your_link')" :description="__('affiliate.link_help')" class="mb-5">
        <div class="flex items-center gap-2 rounded-xl bg-surface-2 px-3 py-2 font-mono text-sm" x-data="{ done: false }">
            <span class="min-w-0 flex-1 truncate" dir="ltr">{{ $link }}</span>
            <button type="button" class="btn btn-secondary btn-sm" @click="navigator.clipboard.writeText('{{ $link }}'); done = true; setTimeout(() => done = false, 1500)"><span x-text="done ? '{{ __('domains.copied') }}' : '{{ __('domains.copy') }}'"></span></button>
        </div>
    </x-ui.card>

    @if ($referrals->isNotEmpty())
        <x-ui.table>
            <thead><tr><th>{{ __('affiliate.restaurant') }}</th><th>{{ __('affiliate.joined') }}</th><th>{{ __('admin.status') }}</th><th class="text-end">{{ __('affiliate.earned') }}</th></tr></thead>
            <tbody>
                @foreach ($referrals as $r)
                    <tr><td class="font-medium">{{ $r->referred?->name }}</td><td class="text-muted">{{ $r->created_at->toFormattedDateString() }}</td>
                        <td><x-ui.badge :tone="$r->status === 'rewarded' ? 'success' : 'neutral'">{{ __('affiliate.status_'.$r->status) }}</x-ui.badge></td>
                        <td class="tnum text-end">{{ $r->status === 'rewarded' ? number_format((float) $r->reward, 2).' '.$currency : '—' }}</td></tr>
                @endforeach
            </tbody>
        </x-ui.table>
    @endif
</x-layouts.app>
