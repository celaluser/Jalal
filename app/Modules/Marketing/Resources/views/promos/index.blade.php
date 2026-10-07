<x-layouts.app :title="__('marketing.promos_title')">
    <x-ui.page-header :title="__('marketing.promos_title')" :description="__('marketing.promos_sub')">
        <x-slot:actions><a href="{{ route('promos.create') }}" class="btn btn-primary"><x-ui.icon name="plus" size="4" />{{ __('marketing.new_promo') }}</a></x-slot:actions>
    </x-ui.page-header>

    @if ($promos->isEmpty())
        <div class="card"><x-ui.empty icon="tag" :title="__('marketing.promos_empty')" :text="__('marketing.promos_empty_text')" /></div>
    @else
        <ul class="grid gap-3">
            @foreach ($promos as $p)
                @php
                    $state = ! $p->is_active ? 'off' : ($p->ends_at && $p->ends_at->isPast() ? 'expired' : ($p->starts_at && $p->starts_at->isFuture() ? 'scheduled' : ($p->exhausted() ? 'used_up' : 'active')));
                    $off = $p->type === 'percent' ? __('marketing.reward_percent', ['value' => $p->value]) : __('marketing.reward_fixed', ['amount' => $restaurant->money($p->value / 100)]);
                @endphp
                <li class="card flex flex-wrap items-center gap-4 p-4">
                    <div class="grid size-12 shrink-0 place-items-center rounded-xl bg-brand-100 text-brand-900 dark:bg-brand-900/40 dark:text-brand-100"><x-ui.icon :name="$p->isLoyaltyReward() ? 'gift' : 'tag'" size="5" /></div>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2"><span class="font-mono text-base font-bold tracking-wide" dir="ltr">{{ $p->code }}</span>
                            @if ($p->isLoyaltyReward())<x-ui.badge tone="info">{{ __('marketing.reward_badge') }}</x-ui.badge>@endif</p>
                        <p class="truncate text-sm text-muted">{{ $off }}@if ($p->min_order_cents) · {{ __('marketing.min_order_label', ['amount' => $restaurant->money($p->min_order_cents / 100)]) }}@endif
                            @if ($p->customer) · {{ __('marketing.reward_for', ['name' => $p->customer->displayName()]) }}@elseif ($p->description) · {{ $p->description }}@endif</p>
                    </div>
                    <div class="tnum text-sm text-muted"><bdi>{{ $p->uses_count }}{{ $p->max_uses ? ' / '.$p->max_uses : '' }}</bdi> {{ __('marketing.promo_uses') }}</div>
                    <x-ui.badge :tone="['active' => 'success', 'off' => 'neutral', 'expired' => 'danger', 'scheduled' => 'info', 'used_up' => 'warning'][$state]" dot>{{ __('marketing.status_'.$state) }}</x-ui.badge>
                    <div class="flex items-center gap-1">
                        @unless ($p->isLoyaltyReward())<a href="{{ route('promos.edit', $p->id) }}" class="btn btn-ghost btn-sm" aria-label="{{ __('admin.edit') }}"><x-ui.icon name="pen" size="4" /></a>@endunless
                        <form method="POST" action="{{ route('promos.toggle', $p->id) }}">@csrf<button class="btn btn-ghost btn-sm">{{ $p->is_active ? __('marketing.turn_off') : __('marketing.turn_on') }}</button></form>
                        <form method="POST" action="{{ route('promos.destroy', $p->id) }}" onsubmit="return confirm('{{ __('marketing.delete_promo_confirm') }}')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" aria-label="{{ __('admin.delete') }}"><x-ui.icon name="trash" size="4" /></button></form>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $promos->links() }}</div>
    @endif
</x-layouts.app>
