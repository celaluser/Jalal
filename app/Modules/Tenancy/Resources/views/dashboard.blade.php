<x-layouts.app :title="__('ui.dashboard')">
    <x-ui.page-header :title="__('ui.welcome', ['name' => strtok($user->name, ' ')])" :description="$restaurant ? __('tenancy.dashboard_sub', ['restaurant' => $restaurant->name]) : null" />

    @if ($restaurant)
        <div class="grid gap-5 lg:grid-cols-3">
            {{-- Public menu address: the thing a restaurant shares first --}}
            <div class="relative flex flex-col justify-between gap-8 overflow-hidden rounded-2xl bg-ink-950 p-6 text-white shadow-card lg:col-span-2" x-data="{ copied: false }">
                <x-ui.qr-pattern class="pointer-events-none absolute -end-8 -top-8 size-52 text-white/[0.07]" :seed="9" />
                <div class="relative"><p class="text-sm font-medium text-ink-300">{{ __('tenancy.menu_address') }}</p>
                <p class="display mt-2 break-all text-2xl font-semibold sm:text-3xl">{{ $menuUrl }}</p></div>
                <div class="relative flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary btn-sm" x-on:click="navigator.clipboard.writeText(@js($menuUrl)).then(() => { copied = true; setTimeout(() => copied = false, 1800) })">
                        <x-ui.icon name="check" size="4" x-show="copied" x-cloak /><span x-text="copied ? @js(__('tenancy.copied')) : @js(__('tenancy.copy_link'))"></span>
                    </button>
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

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
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
    @endif
</x-layouts.app>
