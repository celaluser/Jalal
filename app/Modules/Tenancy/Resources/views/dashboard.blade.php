<x-layouts.app :title="__('ui.dashboard')">
    <x-ui.page-header :title="__('ui.welcome', ['name' => strtok($user->name, ' ')])" :description="$restaurant ? __('tenancy.dashboard_sub', ['restaurant' => $restaurant->name]) : null" />

    @if ($restaurant)
        <div class="grid items-start gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                {{-- Public menu address: the thing a restaurant shares first --}}
                <div class="relative flex flex-col justify-between gap-8 overflow-hidden rounded-2xl bg-ink-950 p-6 text-white shadow-card" x-data="{ copied: false }">
                    <x-ui.qr-pattern class="pointer-events-none absolute -end-8 -top-8 size-52 text-white/[0.07]" :seed="9" />
                    <div class="relative"><p class="text-sm font-medium text-ink-300">{{ __('tenancy.menu_address') }}</p>
                    <p class="display mt-2 break-all text-2xl font-semibold sm:text-3xl">{{ $menuUrl }}</p></div>
                    <div class="relative flex flex-wrap gap-2">
                        <button type="button" class="btn btn-primary btn-sm" x-on:click="navigator.clipboard.writeText(@js($menuUrl)).then(() => { copied = true; setTimeout(() => copied = false, 1800) })">
                            <x-ui.icon name="check" size="4" x-show="copied" x-cloak /><span x-text="copied ? @js(__('tenancy.copied')) : @js(__('tenancy.copy_link'))"></span>
                        </button>
                    </div>
                </div>

            @if ($steps && collect($steps)->contains(fn ($st) => ! $st['done']))
                <x-ui.card :title="__('tenancy.setup_title')">
                    <p class="-mt-1 mb-4 text-sm text-muted">{{ __('tenancy.setup_text', ['done' => collect($steps)->where('done', true)->count(), 'total' => count($steps)]) }}</p>
                    <ul class="grid gap-2 sm:grid-cols-2">
                        @foreach ($steps as $step)
                            <li><a href="{{ route($step['route']) }}" class="flex items-center gap-3 rounded-xl border border-line p-3 transition hover:border-line-strong hover:bg-surface-2/60">
                                <span @class(['grid size-7 shrink-0 place-items-center rounded-full', 'bg-accent-100 text-accent-800 dark:bg-accent-900/40 dark:text-accent-200' => $step['done'], 'border border-line-strong text-muted' => ! $step['done']])>
                                    @if ($step['done'])<x-ui.icon name="check" size="4" />@else<span class="text-xs font-semibold">{{ $loop->iteration }}</span>@endif
                                </span>
                                <span class="min-w-0"><span @class(['block text-sm font-medium', 'line-through opacity-60' => $step['done']])>{{ __('tenancy.step_'.$step['key']) }}</span><span class="block truncate text-xs text-muted">{{ __('tenancy.step_'.$step['key'].'_text') }}</span></span>
                            </a></li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            @if ($stats)
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <x-ui.stat icon="receipt" :label="__('tenancy.orders_today')" :value="number_format($stats['today']['orders'])" :hint="$stats['orders_delta'] !== null ? __('tenancy.vs_yesterday', ['change' => ($stats['orders_delta'] > 0 ? '+' : '').$stats['orders_delta'].'%']) : null" />
                    <x-ui.stat icon="wallet" :label="__('tenancy.revenue_today')" :value="$restaurant->money($stats['today']['revenue'] / 100)" :hint="$stats['revenue_delta'] !== null ? __('tenancy.vs_yesterday', ['change' => ($stats['revenue_delta'] > 0 ? '+' : '').$stats['revenue_delta'].'%']) : null" />
                    <x-ui.stat icon="clock" :label="__('tenancy.open_orders')" :value="number_format($stats['open'])" />
                    <x-ui.stat icon="star" :label="__('tenancy.average_order')" :value="$restaurant->money($stats['week']['average'] / 100)" :hint="__('tenancy.last_7_days')" />
                </div>
                <x-ui.card><x-ui.bar-chart :title="__('tenancy.revenue_7d', ['currency' => $restaurant->currency_code])" :labels="$stats['chart']['labels']" :values="$stats['chart']['values']" :decimals="2" /></x-ui.card>
            @endif

            @if ($recent->isNotEmpty())
                <x-ui.card :title="__('tenancy.recent_orders')" :pad="false">
                    <ul class="divide-y divide-line">
                        @foreach ($recent as $order)
                            <li><a href="{{ route('orders.show', $order->id) }}" class="flex items-center gap-3 px-5 py-3 transition hover:bg-surface-2/60">
                                <span class="tnum text-sm font-semibold">{{ $order->label() }}</span>
                                <span class="min-w-0 flex-1 truncate text-xs text-muted">{{ $order->created_at->diffForHumans() }}</span>
                                <span class="tnum text-sm">{{ $restaurant->money($order->total_cents / 100) }}</span>
                                <x-ui.status :value="$order->status" :label="ucfirst($order->status)" />
                            </a></li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                @can('support.manage')
                    <a href="{{ route('support.index') }}" class="card group flex items-start gap-4 p-5 transition hover:border-line-strong hover:shadow-pop">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-surface-2"><x-ui.icon name="life-buoy" size="5" /></span>
                        <span><span class="block font-semibold">{{ __('tenancy.help_title') }}</span><span class="mt-0.5 block text-sm text-muted">{{ __('tenancy.help_text') }}</span></span>
                    </a>
                @endcan
                <a href="{{ route('two-factor.show') }}" class="card group flex items-start gap-4 p-5 transition hover:border-line-strong hover:shadow-pop">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-surface-2"><x-ui.icon name="shield" size="5" /></span>
                    <span><span class="block font-semibold">{{ __('auth.two_factor') }}</span><span class="mt-0.5 block text-sm text-muted">{{ $user->hasTwoFactorEnabled() ? __('tenancy.two_factor_on') : __('tenancy.two_factor_off') }}</span></span>
                </a>
            </div>
            </div>

            <x-ui.card :title="__('tenancy.your_plan')">
                @if ($plan)
                    <div class="flex items-start justify-between gap-3">
                        <div><p class="display text-2xl font-semibold">{{ $plan->name }}</p>
                            <p class="mt-0.5 text-sm text-muted">@if ($subscription->ends_at){{ $subscription->status === 'trialing' ? __('tenancy.trial_ends') : __('tenancy.renews') }} {{ $subscription->ends_at->toFormattedDateString() }}@else{{ __('tenancy.no_end') }}@endif</p></div>
                        <x-ui.status :value="$subscription->status" :label="__('admin.subscriptions.status_'.$subscription->status)" />
                    </div>
                    <dl class="mt-5 space-y-2 text-sm">
                        @foreach ($limits as $limit)
                            <div class="flex justify-between"><dt class="text-muted">{{ __('admin.plans.limit_'.$limit) }}</dt><dd class="tnum font-medium">{{ $plan->limit($limit) ?? __('site.pricing.unlimited') }}</dd></div>
                        @endforeach
                    </dl>
                @else
                    <x-ui.empty icon="layers" :title="__('tenancy.no_plan')" :text="__('tenancy.no_plan_text')" class="!py-6" />
                @endif
            </x-ui.card>
        </div>
    @endif
</x-layouts.app>
