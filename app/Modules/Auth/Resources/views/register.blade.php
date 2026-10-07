<x-layouts.guest :title="__('auth.register')" :heading="__('auth.register_heading')" :subheading="__('auth.register_sub')">
    <form method="POST" action="{{ url('/register') }}" class="space-y-4">
        @csrf
        <x-ui.input name="restaurant_name" :label="__('auth.restaurant_name')" required autofocus />
        <x-ui.input name="name" :label="__('auth.name')" required autocomplete="name" />
        <x-ui.input name="email" type="email" :label="__('auth.email')" required autocomplete="username" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="password" type="password" :label="__('auth.password_label')" required autocomplete="new-password" :hint="__('auth.password_hint')" />
            <x-ui.input name="password_confirmation" type="password" :label="__('auth.password_confirm')" required autocomplete="new-password" />
        </div>
        <x-recaptcha />
        <x-ui.button size="lg">{{ __('auth.register') }}</x-ui.button>
    </form>
    <p class="text-center text-sm text-muted">{{ __('auth.have_account') }} <a class="link" href="{{ route('login') }}">{{ __('auth.login') }}</a></p>
</x-layouts.guest>
