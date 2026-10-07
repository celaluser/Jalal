<x-layouts.app :title="__('onboarding.settings_title')">
    <x-ui.page-header :title="__('onboarding.settings_title')" :description="__('onboarding.settings_sub')" />
    <div class="space-y-6">
        <x-ui.card :title="__('onboarding.step_profile')">
            <form method="POST" action="{{ route('restaurant.settings.profile') }}" class="space-y-4">
                @csrf @method('PUT')
                @include('tenancy::partials-profile')
                <div class="flex justify-end pt-2"><x-ui.button>{{ __('admin.save') }}</x-ui.button></div>
            </form>
        </x-ui.card>
        <x-ui.card :title="__('onboarding.step_branding')">
            <form method="POST" action="{{ route('restaurant.settings.branding') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @include('tenancy::partials-branding')
                <div class="flex justify-end pt-2"><x-ui.button>{{ __('admin.save') }}</x-ui.button></div>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>
