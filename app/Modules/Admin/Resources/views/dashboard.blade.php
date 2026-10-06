<x-layouts.admin :title="__('admin.nav.dashboard')">
    <div class="space-y-6">
        <div class="grid gap-4 lg:grid-cols-3">
            <x-ui.stat hero :label="__('admin.dashboard.mrr')" :value="number_format($totals['mrr'], 2).' '.$currency" class="lg:col-span-1" />
            <div class="grid grid-cols-2 gap-4 lg:col-span-2 lg:grid-cols-4">
                <x-ui.stat :label="__('admin.dashboard.restaurants')" :value="number_format($totals['restaurants'])" />
                <x-ui.stat :label="__('admin.dashboard.active_subscriptions')" :value="number_format($totals['active_subscriptions'])" />
                <x-ui.stat :label="__('admin.dashboard.trials')" :value="number_format($totals['trials'])" />
                <x-ui.stat :label="__('admin.dashboard.new_30d')" :value="number_format($totals['new_30d'])" />
            </div>
        </div>
        <div class="grid gap-4 xl:grid-cols-2">
            <x-ui.card><x-ui.bar-chart :title="__('admin.dashboard.signups_chart')" :labels="$signups['labels']" :values="$signups['values']" integer /></x-ui.card>
            <x-ui.card><x-ui.bar-chart :title="__('admin.dashboard.revenue_chart', ['currency' => $currency])" :labels="$revenue['labels']" :values="$revenue['values']" :unit="$currency" /></x-ui.card>
        </div>
        @if ($totals['suspended'] > 0)
            <p class="text-sm text-gray-500">{{ __('admin.dashboard.suspended_note', ['count' => $totals['suspended']]) }}</p>
        @endif
    </div>
</x-layouts.admin>
