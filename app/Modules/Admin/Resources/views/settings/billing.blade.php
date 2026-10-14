<x-layouts.admin :title="__('admin.nav.billing_settings')">
    <x-ui.page-header :title="__('admin.nav.billing_settings')" :description="__('admin.billing.description')" />
    <form method="POST" action="{{ route('admin.settings.billing.update') }}" class="max-w-2xl space-y-5">
        @csrf @method('PUT')
        <x-ui.card :title="__('admin.billing.tax')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select name="currency" :label="__('admin.billing.currency')" :value="$values['billing.currency'] ?? 'USD'" :options="$currencies->pluck('code', 'code')->all()" />
                <x-ui.input name="tax_name" :label="__('admin.billing.tax_name')" :value="old('tax_name', $values['billing.tax_name'] ?? 'VAT')" />
                <x-ui.input name="tax_rate" type="number" step="0.01" min="0" max="100" :label="__('admin.billing.tax_rate')" :value="old('tax_rate', $values['billing.tax_rate'] ?? 0)" required />
                <div class="flex items-end pb-2.5"><x-ui.checkbox name="prices_include_tax" :label="__('admin.billing.prices_include_tax')" :checked="$values['billing.prices_include_tax']" /></div>
                <div class="flex items-end pb-2.5"><x-ui.checkbox name="affiliate_enabled" :label="__('admin.billing.affiliate_enabled')" :checked="$values['affiliate.enabled']" /></div>
                <x-ui.input name="affiliate_percent" type="number" step="0.5" min="0" max="100" :label="__('admin.billing.affiliate_percent')" :value="$values['affiliate.percent']" />
                <div class="flex items-end pb-2.5"><x-ui.checkbox name="proration" :label="__('admin.billing.proration')" :checked="$values['billing.proration']" /></div>
            </div>
        </x-ui.card>
        <x-ui.card :title="__('admin.billing.company')">
            <div class="space-y-4">
                <x-ui.input name="company_name" :label="__('admin.billing.company_name')" :value="old('company_name', $values['billing.company_name'] ?? '')" />
                <x-ui.input name="company_address" :label="__('admin.billing.company_address')" :value="old('company_address', $values['billing.company_address'] ?? '')" />
                <x-ui.input name="company_tax_id" :label="__('admin.billing.company_tax_id')" :value="old('company_tax_id', $values['billing.company_tax_id'] ?? '')" />
            </div>
        </x-ui.card>
        <x-ui.button :block="false" size="lg">{{ __('admin.save') }}</x-ui.button>
    </form>
</x-layouts.admin>
