<x-layouts.admin :title="__('platform.new_restaurant')">
    <x-ui.page-header :title="__('platform.new_restaurant')" :description="__('platform.new_restaurant_sub')" :back="['url' => route('admin.restaurants.index'), 'label' => __('admin.nav.restaurants')]" />

    <form method="POST" action="{{ route('admin.restaurants.store') }}" class="max-w-3xl space-y-5">
        @csrf
        <x-ui.card :title="__('platform.restaurant')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="restaurant_name" :label="__('admin.restaurants.name')" :value="old('restaurant_name')" required maxlength="120" />
                <x-ui.input name="slug" :label="__('admin.restaurants.slug')" :value="old('slug')" :placeholder="__('platform.slug_auto')" />
                <x-ui.select name="locale" :label="__('admin.restaurants.locale')" :options="$languages" :value="$defaults['locale']" />
                <x-ui.select name="currency_code" :label="__('platform.currency')" :options="$currencies" :value="$defaults['currency']" :placeholder="__('platform.currency_default')" />
                <x-ui.input name="timezone" :label="__('platform.timezone')" :value="old('timezone', $defaults['timezone'])" placeholder="Europe/Istanbul" />
                <x-ui.select name="theme" :label="__('platform.theme')" :options="$themes" :placeholder="__('platform.theme_default')" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('platform.owner')" :description="__('platform.owner_sub')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="owner_name" :label="__('platform.owner_name')" :value="old('owner_name')" required maxlength="120" />
                <x-ui.input name="owner_email" type="email" :label="__('platform.owner_email')" :value="old('owner_email')" required maxlength="190" />
                <div class="sm:col-span-2"><x-ui.input name="password" type="password" :label="__('platform.password')" :hint="__('platform.password_hint')" autocomplete="new-password" /></div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('platform.plan_title')">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.select name="plan_id" :label="__('admin.subscriptions.assign_plan')" :options="$plans->pluck('name', 'id')->all()" :placeholder="__('platform.no_plan')" />
                <x-ui.input name="ends_at" type="date" :label="__('admin.subscriptions.custom_end')" />
                <div class="pb-2.5 sm:self-end"><x-ui.checkbox name="start_trial" :label="__('platform.start_trial')" /></div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('platform.showcase')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select name="badge" :label="__('platform.badge')" :options="collect(\App\Modules\Tenancy\Models\Restaurant::BADGES)->mapWithKeys(fn ($b) => [$b => __('platform.badge_'.$b)])->all()" :placeholder="__('platform.badge_none')" />
                <div class="space-y-2 pt-1 sm:pt-7">
                    <x-ui.checkbox name="is_premium" :label="__('platform.is_premium')" />
                    <x-ui.checkbox name="is_featured" :label="__('platform.is_featured')" />
                </div>
            </div>
        </x-ui.card>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.restaurants.index') }}" class="btn btn-secondary">{{ __('admin.cancel') }}</a>
            <x-ui.button :block="false">{{ __('platform.create') }}</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
