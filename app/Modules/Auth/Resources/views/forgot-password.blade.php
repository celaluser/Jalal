<x-layouts.guest :title="__('auth.forgot_password')" :heading="__('auth.forgot_password')" :subheading="__('auth.forgot_help')">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-ui.input name="email" type="email" :label="__('auth.email')" required autofocus />
        <x-recaptcha />
        <x-ui.button size="lg">{{ __('auth.send_reset_link') }}</x-ui.button>
    </form>
    <p class="text-center text-sm"><a class="link" href="{{ route('login') }}">← {{ __('auth.login') }}</a></p>
</x-layouts.guest>
