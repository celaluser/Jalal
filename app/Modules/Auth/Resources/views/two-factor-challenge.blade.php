<x-layouts.guest :title="__('auth.two_factor')" :heading="__('auth.two_factor')" :subheading="__('auth.two_factor_help')">
    <form method="POST" action="{{ url('/two-factor-challenge') }}" class="space-y-4">
        @csrf
        <x-ui.input name="code" :label="__('auth.code')" required autofocus autocomplete="one-time-code" inputmode="text" class="text-center font-mono text-lg tracking-widest" />
        <x-ui.button size="lg">{{ __('auth.verify') }}</x-ui.button>
    </form>
</x-layouts.guest>
