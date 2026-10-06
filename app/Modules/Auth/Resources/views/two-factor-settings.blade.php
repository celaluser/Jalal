<x-layouts.app :title="__('auth.two_factor')">
    <x-ui.card class="mx-auto max-w-lg">
        <h1 class="mb-4 text-xl font-semibold">{{ __('auth.two_factor') }}</h1>

        @if ($recoveryCodes)
            <x-ui.alert>{{ __('auth.recovery_codes_help') }}</x-ui.alert>
            <ul class="mb-4 grid grid-cols-2 gap-1 font-mono text-sm">
                @foreach ($recoveryCodes as $code)<li>{{ $code }}</li>@endforeach
            </ul>
        @endif

        @if (auth()->user()->hasTwoFactorEnabled())
            <p class="mb-4 text-sm">{{ __('auth.two_factor_enabled') }}</p>
            <form method="POST" action="{{ route('two-factor.disable') }}" class="space-y-4">
                @csrf @method('DELETE')
                <x-ui.input name="password" type="password" :label="__('auth.password_label')" required />
                <x-ui.button variant="danger">{{ __('auth.disable') }}</x-ui.button>
            </form>
        @else
            <p class="mb-3 text-sm text-gray-500">{{ __('auth.two_factor_setup_help') }}</p>
            <div class="mb-3 inline-block rounded-lg bg-white p-2">{!! $qr !!}</div>
            <p class="mb-4 break-all font-mono text-xs">{{ $secret }}</p>
            <form method="POST" action="{{ route('two-factor.enable') }}" class="space-y-4">
                @csrf
                <x-ui.input name="code" :label="__('auth.code')" required inputmode="numeric" autocomplete="one-time-code" />
                <x-ui.button>{{ __('auth.enable') }}</x-ui.button>
            </form>
        @endif
    </x-ui.card>
</x-layouts.app>
