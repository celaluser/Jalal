@php
    $editing = $promo !== null;
    $src = $promo ?? ($preset ?? null); // a saved promo, or a ready-made template
    $type = old('type', $src?->type ?? 'percent');
    $value = old('value', $src ? ($src->type === 'percent' ? $src->value : $src->value / 100) : '');
@endphp
<x-layouts.app :title="$editing ? __('marketing.edit_promo') : __('marketing.new_promo')">
    <x-ui.page-header :title="$editing ? __('marketing.edit_promo') : __('marketing.new_promo')" :back="['url' => route('promos.index'), 'label' => __('marketing.promos_title')]" />

    <form method="POST" action="{{ $editing ? route('promos.update', $promo->id) : route('promos.store') }}" class="card card-pad max-w-2xl space-y-5" x-data="{ type: @js($type) }">
        @csrf @if ($editing) @method('PUT') @endif
        @unless ($editing)
            <div><p class="mb-2 text-sm font-medium">{{ __('marketing.templates') }}</p><div class="flex flex-wrap gap-2">@foreach ($templates as $t)<a href="{{ route('promos.create', ['template' => $t]) }}" class="chip">{{ __('marketing.template_'.$t) }}</a>@endforeach</div></div>
        @endunless
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="code" :label="__('marketing.promo_code')" :value="$src?->code" required autocomplete="off" class="uppercase" dir="ltr" maxlength="40" />
            <x-ui.input name="description" :label="__('marketing.promo_desc')" :value="$src?->description" :hint="__('marketing.promo_desc_help')" maxlength="160" />
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
            <x-ui.input name="min_order" type="number" step="0.01" min="0" :value="$src ? $src->min_order_cents / 100 : ''" :label="__('marketing.promo_min')" />
            <x-ui.input name="max_uses" type="number" min="1" :value="$src?->max_uses" :label="__('marketing.promo_max_uses')" :hint="__('marketing.promo_max_help')" />
            <x-ui.input name="starts_at" type="date" :value="$src?->starts_at?->toDateString()" :label="__('marketing.promo_starts')" />
            <x-ui.input name="ends_at" type="date" :value="$src?->ends_at?->toDateString()" :label="__('marketing.promo_ends')" />
        </div>
        <x-ui.checkbox name="is_active" :label="__('marketing.promo_active')" :checked="$src?->is_active ?? true" />
        <div class="flex gap-3"><x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button><a href="{{ route('promos.index') }}" class="btn btn-secondary">{{ __('admin.cancel') }}</a></div>
    </form>
</x-layouts.app>
