<x-layouts.app :title="__('auth.two_factor')">
    <x-ui.page-header :title="__('auth.two_factor')" :description="auth()->user()->hasTwoFactorEnabled() ? __('auth.two_factor_enabled') : __('auth.two_factor_setup_help')" />
    <div class="max-w-xl space-y-5">
        @if ($recoveryCodes)
            <x-ui.alert type="warning">{{ __('auth.recovery_codes_help') }}</x-ui.alert>
            <ul class="card grid grid-cols-2 gap-2 p-5 font-mono text-sm">@foreach ($recoveryCodes as $code)<li>{{ $code }}</li>@endforeach</ul>
        @endif

        @if (auth()->user()->hasTwoFactorEnabled())
            <x-ui.card :title="__('auth.disable')">
                <form method="POST" action="{{ route('two-factor.disable') }}" class="space-y-4">
                    @csrf @method('DELETE')
                    <x-ui.input name="password" type="password" :label="__('auth.password_label')" required />
                    <x-ui.button variant="danger" :block="false">{{ __('auth.disable') }}</x-ui.button>
                </form>
            </x-ui.card>
        @else
            <x-ui.card>
                <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                    <div class="shrink-0 rounded-2xl border border-line bg-white p-3">{!! $qr !!}</div>
                    <div class="min-w-0 space-y-4">
                        <div><p class="eyebrow mb-1">{{ __('auth.manual_key') }}</p><p class="break-all font-mono text-sm">{{ $secret }}</p></div>
                        <form method="POST" action="{{ route('two-factor.enable') }}" class="space-y-4">
                            @csrf
                            <x-ui.input name="code" :label="__('auth.code')" required inputmode="numeric" autocomplete="one-time-code" />
                            <x-ui.button :block="false">{{ __('auth.enable') }}</x-ui.button>
                        </form>
                    </div>
                </div>
            </x-ui.card>
        @endif
    </div>
</x-layouts.app>
