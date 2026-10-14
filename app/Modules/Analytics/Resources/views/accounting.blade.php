<x-layouts.app :title="__('analytics.acc_title')">
    <x-ui.page-header :title="__('analytics.acc_title')" :description="__('analytics.acc_sub')">
        <x-slot:actions><a href="{{ route('reports.index') }}" class="btn btn-secondary">{{ __('analytics.title') }}</a></x-slot:actions>
    </x-ui.page-header>
    @if ($errors->any())<x-ui.alert type="danger" class="mb-4">{{ $errors->first() }}</x-ui.alert>@endif

    <form method="POST" action="{{ route('reports.accounting.download') }}" class="max-w-3xl space-y-5">
        @csrf
        <input type="hidden" name="range" value="custom">
        <x-ui.card :title="__('analytics.acc_period')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="from" type="date" :label="__('analytics.from')" :value="$period['from']->toDateString()" required />
                <x-ui.input name="to" type="date" :label="__('analytics.to')" :value="$period['to']->toDateString()" required />
            </div>
        </x-ui.card>
        <x-ui.card :title="__('analytics.acc_format')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select name="format" :label="__('analytics.acc_package')" :options="['xero' => 'Xero (Sales invoices)', 'quickbooks' => 'QuickBooks Online (Invoices)', 'generic' => __('analytics.acc_generic')]" value="xero" />
                <x-ui.select name="mode" :label="__('analytics.acc_mode')" :options="['daily' => __('analytics.acc_daily'), 'orders' => __('analytics.acc_orders')]" value="daily" />
                <x-ui.input name="account" :label="__('analytics.acc_account')" value="200" maxlength="30" />
                <x-ui.input name="tax_label" :label="__('analytics.acc_tax')" value="Tax on Sales" maxlength="40" />
            </div>
            <p class="mt-3 text-xs text-muted">{{ __('analytics.acc_help') }}</p>
        </x-ui.card>
        <x-ui.button :block="false"><x-ui.icon name="download" size="4" />{{ __('analytics.acc_download') }}</x-ui.button>
    </form>
</x-layouts.app>
