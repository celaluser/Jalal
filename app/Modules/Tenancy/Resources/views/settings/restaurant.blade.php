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
        @php($support = app(\App\Modules\Tenancy\Services\SupportAccess::class))
        @if ($support->required())
            <x-ui.card :title="__('support_access.card_title')">
                <p class="text-sm text-muted">{{ __('support_access.card_text') }}</p>
                @php($until = $support->until(auth()->user()->restaurant))
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-medium">{{ $until ? __('support_access.allowed_until', ['time' => $until->isoFormat('LLL')]) : __('support_access.not_allowed') }}</p>
                    @if ($until)
                        <form method="POST" action="{{ route('support-access.withdraw') }}">@csrf @method('DELETE')<x-ui.button variant="secondary">{{ __('support_access.withdraw') }}</x-ui.button></form>
                    @else
                        <form method="POST" action="{{ route('support-access.grant') }}">@csrf<x-ui.button>{{ __('support_access.allow') }}</x-ui.button></form>
                    @endif
                </div>
            </x-ui.card>
        @endif
    </div>
</x-layouts.app>
