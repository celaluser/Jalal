<x-layouts.admin :title="$coupon->exists ? $coupon->code : __('admin.coupons.new')">
    <form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" class="mx-auto max-w-2xl">
        @csrf @if ($coupon->exists) @method('PUT') @endif
        <x-ui.card class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="code" :label="__('admin.coupons.code')" :value="old('code', $coupon->code)" required />
            <x-ui.select name="type" :label="__('admin.coupons.type')" :value="$coupon->type" :options="['percent' => __('admin.coupons.type_percent'), 'fixed' => __('admin.coupons.type_fixed')]" />
            <x-ui.input name="value" type="number" step="0.01" min="0" :label="__('admin.coupons.value')" :value="old('value', $coupon->value)" required />
            <x-ui.input name="max_uses" type="number" min="1" :label="__('admin.coupons.max_uses')" :value="old('max_uses', $coupon->max_uses)" placeholder="∞" />
            <x-ui.input name="starts_at" type="date" :label="__('admin.coupons.starts')" :value="old('starts_at', $coupon->starts_at?->toDateString())" />
            <x-ui.input name="expires_at" type="date" :label="__('admin.coupons.expires')" :value="old('expires_at', $coupon->expires_at?->toDateString())" />
            <fieldset class="sm:col-span-2">
                <legend class="mb-1 text-sm font-medium">{{ __('admin.coupons.plans') }}</legend>
                <p class="mb-2 text-xs text-gray-500">{{ __('admin.coupons.plans_help') }}</p>
                <div class="flex flex-wrap gap-4">
                    @foreach ($plans as $plan)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="plan_ids[]" value="{{ $plan->id }}" @checked(in_array($plan->id, old('plan_ids', $coupon->plan_ids ?? []))) class="rounded"> {{ $plan->name }}</label>
                    @endforeach
                </div>
            </fieldset>
            <x-ui.checkbox name="is_active" :label="__('admin.active')" :checked="$coupon->is_active" />
            <div class="sm:col-span-2"><x-ui.button class="!w-auto">{{ __('admin.save') }}</x-ui.button></div>
        </x-ui.card>
    </form>
</x-layouts.admin>
