<x-layouts.admin :title="__('admin.nav.dashboard')">
    <x-ui.page-header :title="__('admin.dashboard.welcome', ['name' => strtok(auth()->user()->name, ' ')])" :description="__('admin.dashboard.subtitle')">
        <x-slot:actions>
            <a href="{{ route('admin.restaurants.index') }}" class="btn btn-secondary btn-sm"><x-ui.icon name="store" size="4" />{{ __('admin.nav.restaurants') }}</a>
            <a href="{{ route('admin.plans.create') }}" class="btn btn-primary btn-sm"><x-ui.icon name="plus" size="4" />{{ __('admin.plans.new') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($isDemo)
        <x-ui.alert type="warning">{{ __('admin.dashboard.demo_notice') }} <a class="font-medium underline" href="{{ route('admin.settings.section', 'general') }}">{{ __('admin.dashboard.demo_turn_off') }}</a></x-ui.alert>
    @endif

    @if (collect($steps)->contains(fn ($st) => ! $st['done']))
        <x-ui.card :title="__('admin.dashboard.setup_title')">
            <p class="-mt-1 mb-4 text-sm text-muted">{{ __('admin.dashboard.setup_text', ['done' => collect($steps)->where('done', true)->count(), 'total' => count($steps)]) }}</p>
            <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($steps as $step)
                    <li><a href="{{ $step['route'] }}" class="flex items-center gap-3 rounded-xl border border-line p-3 transition hover:border-line-strong hover:bg-surface-2/60">
                        <span @class(['grid size-7 shrink-0 place-items-center rounded-full', 'bg-accent-100 text-accent-800 dark:bg-accent-900/40 dark:text-accent-200' => $step['done'], 'border border-line-strong text-muted' => ! $step['done']])>
                            @if ($step['done'])<x-ui.icon name="check" size="4" />@else<span class="text-xs font-semibold">{{ $loop->iteration }}</span>@endif
                        </span>
                        <span class="min-w-0"><span @class(['block text-sm font-medium', 'line-through opacity-60' => $step['done']])>{{ __('admin.dashboard.step_'.$step['key']) }}</span><span class="block truncate text-xs text-muted">{{ __('admin.dashboard.step_'.$step['key'].'_text') }}</span></span>
                    </a></li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    <div class="grid gap-4 lg:grid-cols-12">
        <x-ui.stat hero :label="__('admin.dashboard.mrr')" :value="number_format($totals['mrr'], 2).' '.$currency" :hint="__('admin.dashboard.mrr_hint')" class="lg:col-span-4" />
        <div class="grid grid-cols-2 gap-4 lg:col-span-8">
            <x-ui.stat icon="store" :label="__('admin.dashboard.restaurants')" :value="number_format($totals['restaurants'])" :hint="$totals['suspended'] ? __('admin.dashboard.suspended_note', ['count' => $totals['suspended']]) : null" />
            <x-ui.stat icon="repeat" :label="__('admin.dashboard.active_subscriptions')" :value="number_format($totals['active_subscriptions'])" />
            <x-ui.stat icon="clock" :label="__('admin.dashboard.trials')" :value="number_format($totals['trials'])" />
            <x-ui.stat icon="sparkles" :label="__('admin.dashboard.new_30d')" :value="number_format($totals['new_30d'])" />
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <x-ui.card><x-ui.bar-chart :title="__('admin.dashboard.signups_chart')" :labels="$signups['labels']" :values="$signups['values']" integer /></x-ui.card>
        <x-ui.card><x-ui.bar-chart :title="__('admin.dashboard.revenue_chart', ['currency' => $currency])" :labels="$revenue['labels']" :values="$revenue['values']" :unit="$currency" /></x-ui.card>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <x-ui.card :title="__('admin.dashboard.latest_restaurants')" :pad="false">
            <ul class="divide-y divide-line">
                @forelse ($latest as $restaurant)
                    <li><a href="{{ route('admin.restaurants.show', $restaurant->id) }}" class="flex items-center gap-3 px-5 py-3 transition hover:bg-surface-2/60">
                        <x-ui.avatar :name="$restaurant->name" size="9" />
                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ $restaurant->name }}</span><span class="block truncate text-xs text-muted">{{ $restaurant->owner?->email }}</span></span>
                        <span class="text-xs text-muted">{{ $restaurant->created_at->diffForHumans() }}</span>
                    </a></li>
                @empty
                    <li><x-ui.empty icon="store" :title="__('admin.empty')" /></li>
                @endforelse
            </ul>
        </x-ui.card>
        <x-ui.card :pad="false">
            <div class="flex items-center justify-between border-b border-line px-5 py-4 sm:px-6">
                <h2 class="text-[15px] font-semibold">{{ __('admin.dashboard.open_tickets') }}</h2>
                @if ($tickets['count'] > 0)<x-ui.badge tone="warning">{{ $tickets['count'] }}</x-ui.badge>@endif
            </div>
            <ul class="divide-y divide-line">
                @forelse ($tickets['latest'] as $ticket)
                    <li><a href="{{ route('admin.tickets.show', $ticket->id) }}" class="flex items-center gap-3 px-5 py-3 transition hover:bg-surface-2/60">
                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ $ticket->subject }}</span><span class="block truncate text-xs text-muted">{{ $restaurantNames[$ticket->restaurant_id] ?? '' }}</span></span>
                        @if ($ticket->priority === 'high')<x-ui.status value="high" :label="__('support.priority_high')" />@endif
                        <span class="text-xs text-muted">{{ $ticket->last_reply_at?->diffForHumans() }}</span>
                    </a></li>
                @empty
                    <li><x-ui.empty icon="check-circle" :title="__('admin.dashboard.no_tickets')" /></li>
                @endforelse
            </ul>
        </x-ui.card>
    </div>
</x-layouts.admin>
