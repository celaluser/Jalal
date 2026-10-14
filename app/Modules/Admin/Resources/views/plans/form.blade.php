<x-layouts.admin :title="$plan->exists ? $plan->name : __('admin.plans.new')">
    <x-ui.page-header :title="$plan->exists ? $plan->name : __('admin.plans.new')" :back="['url' => route('admin.plans.index'), 'label' => __('admin.nav.plans')]" />
    <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="max-w-3xl space-y-5">
        @csrf @if ($plan->exists) @method('PUT') @endif
        <x-ui.card :title="__('admin.plans.basics')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="name" :label="__('admin.plans.name')" :value="old('name', $plan->name)" required />
                <x-ui.input name="slug" :label="__('admin.plans.slug')" :value="old('slug', $plan->slug)" required />
                <div class="sm:col-span-2"><x-ui.input name="description" :label="__('admin.plans.description')" :value="old('description', $plan->description)" /></div>
                <x-ui.select name="interval" :label="__('admin.plans.interval')" :value="$plan->interval" :options="collect(\App\Modules\Billing\Models\Plan::INTERVALS)->mapWithKeys(fn ($i) => [$i => __('billing.interval_'.$i)])->all()" />
                <x-ui.input name="price" type="number" step="0.01" min="0" :label="__('admin.plans.price')" :value="old('price', $plan->price)" required />
                <x-ui.select name="currency_code" :label="__('admin.plans.currency')" :value="$plan->currency_code" :options="$currencies->pluck('code', 'code')->all()" />
                <x-ui.input name="trial_days" type="number" min="0" :label="__('admin.plans.trial_days')" :value="old('trial_days', $plan->trial_days)" />
                <x-ui.input name="sort" type="number" min="0" :label="__('admin.plans.sort')" :value="old('sort', $plan->sort)" />
                <div class="flex items-end gap-6 pb-2.5"><x-ui.checkbox name="is_active" :label="__('admin.active')" :checked="$plan->is_active" /><x-ui.checkbox name="is_featured" :label="__('admin.plans.featured')" :checked="$plan->is_featured" /></div>
            </div>
        </x-ui.card>
        <x-ui.card :title="__('admin.plans.limits')" :description="__('admin.plans.limits_help')">
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach (\App\Modules\Billing\Models\Plan::LIMITS as $key)
                    <x-ui.input :name="'limits['.$key.']'" type="number" min="0" :label="__('admin.plans.limit_'.$key)" :value="old('limits.'.$key, $plan->limits[$key] ?? '')" placeholder="∞" />
                @endforeach
            </div>
        </x-ui.card>
        <x-ui.card :title="__('admin.plans.features')">
            <div class="grid gap-3.5 sm:grid-cols-2">
                @foreach (\App\Modules\Billing\Models\Plan::FEATURES as $key)
                    <x-ui.checkbox :name="'features['.$key.']'" :label="__('admin.plans.feature_'.$key)" :checked="$plan->features[$key] ?? false" />
                @endforeach
            </div>
        </x-ui.card>
        <div class="flex gap-3"><x-ui.button :block="false" size="lg">{{ __('admin.save') }}</x-ui.button><a href="{{ route('admin.plans.index') }}" class="btn btn-ghost btn-lg">{{ __('admin.cancel') }}</a></div>
    </form>
</x-layouts.admin>
