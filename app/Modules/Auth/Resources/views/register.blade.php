<x-layouts.guest :title="__('auth.register')">
    <h1 class="mb-4 text-xl font-semibold">{{ __('auth.register') }}</h1>
    <form method="POST" action="{{ url('/register') }}" class="space-y-4">
        @csrf
        <x-ui.input name="restaurant_name" :label="__('auth.restaurant_name')" required autofocus />
        <x-ui.input name="name" :label="__('auth.name')" required autocomplete="name" />
        <x-ui.input name="email" type="email" :label="__('auth.email')" required autocomplete="username" />
        <x-ui.input name="password" type="password" :label="__('auth.password_label')" required autocomplete="new-password" />
        <x-ui.input name="password_confirmation" type="password" :label="__('auth.password_confirm')" required autocomplete="new-password" />
        <x-recaptcha />
        <x-ui.button>{{ __('auth.register') }}</x-ui.button>
    </form>
    <p class="mt-4 text-sm"><a class="text-brand-600 hover:underline" href="{{ route('login') }}">{{ __('auth.have_account') }}</a></p>
</x-layouts.guest>
