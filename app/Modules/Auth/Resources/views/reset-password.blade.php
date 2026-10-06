<x-layouts.guest :title="__('auth.reset_password')">
    <h1 class="mb-4 text-xl font-semibold">{{ __('auth.reset_password') }}</h1>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-ui.input name="email" type="email" :label="__('auth.email')" :value="$email" required />
        <x-ui.input name="password" type="password" :label="__('auth.password_label')" required autocomplete="new-password" />
        <x-ui.input name="password_confirmation" type="password" :label="__('auth.password_confirm')" required autocomplete="new-password" />
        <x-ui.button>{{ __('auth.reset_password') }}</x-ui.button>
    </form>
</x-layouts.guest>
