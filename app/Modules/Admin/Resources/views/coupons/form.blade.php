<x-layouts.admin :title="$coupon->exists ? $coupon->code : __('admin.coupons.new')">
    <x-ui.page-header :title="$coupon->exists ? $coupon->code : __('admin.coupons.new')" :back="['url' => route('admin.coupons.index'), 'label' => __('admin.nav.coupons')]" />
    <form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" class="max-w-2xl space-y-5">
        @csrf @if ($coupon->exists) @method('PUT') @endif
        <x-ui.card class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="code" :label="__('admin.coupons.code')" :value="old('code', $coupon->code)" required class="font-mono uppercase" />
            <x-ui.select name="type" :label="__('admin.coupons.type')" :value="$coupon->type" :options="['percent' => __('admin.coupons.type_percent'), 'fixed' => __('admin.coupons.type_fixed')]" />
            <x-ui.input name="value" type="number" step="0.01" min="0" :label="__('admin.coupons.value')" :value="old('value', $coupon->value)" required />
            <x-ui.input name="max_uses" type="number" min="1" :label="__('admin.coupons.max_uses')" :value="old('max_uses', $coupon->max_uses)" placeholder="∞" />
            <x-ui.input name="starts_at" type="date" :label="__('admin.coupons.starts')" :value="old('starts_at', $coupon->starts_at?->toDateString())" />
            <x-ui.input name="expires_at" type="date" :label="__('admin.coupons.expires')" :value="old('expires_at', $coupon->expires_at?->toDateString())" />
            <fieldset class="sm:col-span-2">
                <legend class="mb-1 text-sm font-medium">{{ __('admin.coupons.plans') }}</legend>
                <p class="mb-3 text-xs text-muted">{{ __('admin.coupons.plans_help') }}</p>
                <div class="flex flex-wrap gap-x-6 gap-y-3">
                    @foreach ($plans as $plan)
                        <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="plan_ids[]" value="{{ $plan->id }}" @checked(in_array($plan->id, old('plan_ids', $coupon->plan_ids ?? []))) class="check"> {{ $plan->name }}</label>
                    @endforeach
                </div>
            </fieldset>
            <div class="sm:col-span-2"><x-ui.checkbox name="is_active" :label="__('admin.active')" :checked="$coupon->is_active" /></div>
        </x-ui.card>
        <div class="flex gap-3"><x-ui.button :block="false" size="lg">{{ __('admin.save') }}</x-ui.button><a href="{{ route('admin.coupons.index') }}" class="btn btn-ghost btn-lg">{{ __('admin.cancel') }}</a></div>
    </form>
</x-layouts.admin>
