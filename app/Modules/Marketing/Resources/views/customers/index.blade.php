<x-layouts.app :title="__('marketing.customers_title')">
    <x-ui.page-header :title="__('marketing.customers_title')" :description="__('marketing.customers_sub')">
        <x-slot:actions>
            @if ($canManage)<a href="{{ route('customers.export', request()->only(['q', 'filter'])) }}" class="btn btn-secondary"><x-ui.icon name="download" size="4" />{{ __('marketing.export') }}</a>@endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-5 grid gap-3 sm:grid-cols-3">
        <x-ui.stat :label="__('marketing.stat_all')" :value="number_format($totals['all'])" icon="users" />
        <x-ui.stat :label="__('marketing.stat_opted')" :value="number_format($totals['opted'])" icon="mail" />
        <x-ui.stat :label="__('marketing.stat_repeat')" :value="number_format($totals['repeat'])" icon="gift" />
    </div>

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
        <div class="relative min-w-56 flex-1">
            <x-ui.icon name="search" size="4" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-muted" />
            <input type="search" name="q" value="{{ $q }}" class="field ps-9" placeholder="{{ __('marketing.search_customers') }}" aria-label="{{ __('marketing.search_customers') }}">
        </div>
        <select name="filter" class="field !w-auto" onchange="this.form.submit()" aria-label="{{ __('marketing.filter_all') }}">
            <option value="">{{ __('marketing.filter_all') }}</option>
            <option value="opted" @selected($filter === 'opted')>{{ __('marketing.filter_opted') }}</option>
            <option value="repeat" @selected($filter === 'repeat')>{{ __('marketing.filter_repeat') }}</option>
        </select>
        <select name="sort" class="field !w-auto" onchange="this.form.submit()" aria-label="{{ __('marketing.col_customer') }}">
            @foreach (['recent', 'orders', 'spent', 'name'] as $s)<option value="{{ $s }}" @selected($sort === $s)>{{ __('marketing.sort_'.$s) }}</option>@endforeach
        </select>
        <button class="btn btn-secondary">{{ __('admin.search') }}</button>
    </form>

    @if ($customers->isEmpty())
        <div class="card"><x-ui.empty icon="users" :title="__('marketing.customers_empty')" :text="__('marketing.customers_empty_text')" /></div>
    @else
        <x-ui.table>
            <thead><tr><th>{{ __('marketing.col_customer') }}</th><th class="text-end">{{ __('marketing.col_orders') }}</th><th class="text-end">{{ __('marketing.col_spent') }}</th><th>{{ __('marketing.col_last') }}</th><th>{{ __('marketing.col_marketing') }}</th></tr></thead>
            <tbody>
                @foreach ($customers as $c)
                    <tr>
                        <td>
                            <a href="{{ route('customers.show', $c->id) }}" class="flex items-center gap-3">
                                <x-ui.avatar :name="$c->displayName()" />
                                <span class="min-w-0"><span class="block truncate font-semibold hover:underline">{{ $c->name ?: __('marketing.anonymous') }}</span>
                                    <span class="block truncate text-sm text-muted" dir="ltr">{{ $c->email ?: $c->phone }}</span></span>
                            </a>
                        </td>
                        <td class="tnum text-end">{{ $c->orders_count }}</td>
                        <td class="tnum text-end">{{ $restaurant->money($c->total_cents / 100) }}</td>
                        <td class="whitespace-nowrap text-muted">{{ $c->last_order_at?->diffForHumans() }}</td>
                        <td>
                            @if ($c->unsubscribed_at)<x-ui.badge tone="danger">{{ __('marketing.consent_unsub') }}</x-ui.badge>
                            @elseif ($c->canBeEmailed())<x-ui.badge tone="success" dot>{{ __('marketing.consent_yes') }}</x-ui.badge>
                            @else<x-ui.badge>{{ __('marketing.consent_no') }}</x-ui.badge>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <x-slot:footer>@if ($customers->hasPages()){{ $customers->links() }}@endif</x-slot:footer>
        </x-ui.table>
    @endif
</x-layouts.app>
