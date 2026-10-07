@php
    $editing = $promo !== null;
    $type = old('type', $promo?->type ?? 'percent');
    $value = old('value', $promo ? ($promo->type === 'percent' ? $promo->value : $promo->value / 100) : '');
@endphp
<x-layouts.app :title="$editing ? __('marketing.edit_promo') : __('marketing.new_promo')">
    <x-ui.page-header :title="$editing ? __('marketing.edit_promo') : __('marketing.new_promo')" :back="['url' => route('promos.index'), 'label' => __('marketing.promos_title')]" />

    <form method="POST" action="{{ $editing ? route('promos.update', $promo->id) : route('promos.store') }}" class="card card-pad max-w-2xl space-y-5" x-data="{ type: @js($type) }">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="code" :label="__('marketing.promo_code')" :value="$promo?->code" required autocomplete="off" class="uppercase" dir="ltr" maxlength="40" />
            <x-ui.input name="description" :label="__('marketing.promo_desc')" :value="$promo?->description" :hint="__('marketing.promo_desc_help')" maxlength="160" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="type" class="mb-1.5 block text-sm font-medium">{{ __('marketing.promo_type') }}</label>
                <select id="type" name="type" x-model="type" class="field"><option value="percent">{{ __('marketing.type_percent') }}</option><option value="fixed">{{ __('marketing.type_fixed') }}</option></select>
            </div>
            <div>
                <x-ui.input name="value" type="number" step="0.01" min="0.01" :value="$value" :label="__('marketing.promo_value')" required x-bind:max="type === 'percent' ? 100 : 99999" />
                <p class="mt-1.5 text-xs text-muted"><span x-show="type === 'percent'">%</span><span x-show="type === 'fixed'" x-cloak>{{ $restaurant->currency_code }}</span></p>
            </div>
            <x-ui.input name="min_order" type="number" step="0.01" min="0" :value="$promo ? $promo->min_order_cents / 100 : ''" :label="__('marketing.promo_min')" />
            <x-ui.input name="max_uses" type="number" min="1" :value="$promo?->max_uses" :label="__('marketing.promo_max_uses')" :hint="__('marketing.promo_max_help')" />
            <x-ui.input name="starts_at" type="date" :value="$promo?->starts_at?->toDateString()" :label="__('marketing.promo_starts')" />
            <x-ui.input name="ends_at" type="date" :value="$promo?->ends_at?->toDateString()" :label="__('marketing.promo_ends')" />
        </div>
        <x-ui.checkbox name="is_active" :label="__('marketing.promo_active')" :checked="$promo?->is_active ?? true" />
        <div class="flex gap-3"><x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button><a href="{{ route('promos.index') }}" class="btn btn-secondary">{{ __('admin.cancel') }}</a></div>
    </form>
</x-layouts.app>
