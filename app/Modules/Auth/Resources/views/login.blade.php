<x-layouts.guest :title="__('auth.login')">
    <h1 class="mb-4 text-xl font-semibold">{{ __('auth.login') }}</h1>
    @if (session('status'))<x-ui.alert>{{ session('status') }}</x-ui.alert>@endif
    <form method="POST" action="{{ url('/login') }}" class="space-y-4">
        @csrf
        <x-ui.input name="email" type="email" :label="__('auth.email')" required autofocus autocomplete="username" />
        <x-ui.input name="password" type="password" :label="__('auth.password_label')" required autocomplete="current-password" />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" class="rounded"> {{ __('auth.remember') }}</label>
        <x-ui.button>{{ __('auth.login') }}</x-ui.button>
    </form>
    @if (app(\App\Modules\Core\Services\SettingsService::class)->get('auth.google_enabled'))
        <a href="{{ route('google.redirect') }}" class="mt-3 block rounded-lg border border-gray-300 py-2.5 text-center text-sm font-semibold hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">{{ __('auth.login_with_google') }}</a>
    @endif
    <div class="mt-4 flex justify-between text-sm">
        <a class="text-brand-600 hover:underline" href="{{ route('password.request') }}">{{ __('auth.forgot_password') }}</a>
        @if (Route::has('register'))<a class="text-brand-600 hover:underline" href="{{ route('register') }}">{{ __('auth.register') }}</a>@endif
    </div>
</x-layouts.guest>
