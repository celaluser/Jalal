<x-layouts.guest :title="__('auth.reset_password')" :heading="__('auth.reset_password')">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-ui.input name="email" type="email" :label="__('auth.email')" :value="$email" required />
        <x-ui.input name="password" type="password" :label="__('auth.password_label')" required autocomplete="new-password" :hint="__('auth.password_hint')" />
        <x-ui.input name="password_confirmation" type="password" :label="__('auth.password_confirm')" required autocomplete="new-password" />
        <x-ui.button size="lg">{{ __('auth.reset_password') }}</x-ui.button>
    </form>
</x-layouts.guest>
