<x-layouts.guest :title="__('auth.two_factor')">
    <h1 class="mb-2 text-xl font-semibold">{{ __('auth.two_factor') }}</h1>
    <p class="mb-4 text-sm text-gray-500">{{ __('auth.two_factor_help') }}</p>
    <form method="POST" action="{{ url('/two-factor-challenge') }}" class="space-y-4">
        @csrf
        <x-ui.input name="code" :label="__('auth.code')" required autofocus autocomplete="one-time-code" inputmode="text" />
        <x-ui.button>{{ __('auth.verify') }}</x-ui.button>
    </form>
</x-layouts.guest>
