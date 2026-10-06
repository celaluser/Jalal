<x-layouts.guest :title="__('auth.forgot_password')">
    <h1 class="mb-2 text-xl font-semibold">{{ __('auth.forgot_password') }}</h1>
    <p class="mb-4 text-sm text-gray-500">{{ __('auth.forgot_help') }}</p>
    @if (session('status'))<x-ui.alert>{{ session('status') }}</x-ui.alert>@endif
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-ui.input name="email" type="email" :label="__('auth.email')" required autofocus />
        <x-recaptcha />
        <x-ui.button>{{ __('auth.send_reset_link') }}</x-ui.button>
    </form>
</x-layouts.guest>
