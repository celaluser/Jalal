<x-layouts.guest :title="__('auth.login')" :heading="__('auth.login_heading')" :subheading="__('auth.login_sub')">
    <form method="POST" action="{{ url('/login') }}" class="space-y-4">
        @csrf
        <x-ui.input name="email" type="email" :label="__('auth.email')" required autofocus autocomplete="username" />
        <div>
            <div class="mb-1.5 flex items-center justify-between"><label for="password" class="text-sm font-medium">{{ __('auth.password_label') }}</label><a class="link text-xs" href="{{ route('password.request') }}">{{ __('auth.forgot_password') }}</a></div>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="field" @error('password') aria-invalid="true" @enderror>
        </div>
        <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="remember" class="check"> {{ __('auth.remember') }}</label>
        <x-recaptcha />
        <x-ui.button size="lg">{{ __('auth.login') }}</x-ui.button>
    </form>
    @if (app(\App\Modules\Core\Services\SettingsService::class)->get('auth.google_enabled'))
        <div class="relative text-center text-xs text-muted"><span class="relative z-10 bg-canvas px-3">{{ __('auth.or') }}</span><span class="absolute inset-x-0 top-1/2 h-px bg-line"></span></div>
        <a href="{{ route('google.redirect') }}" class="btn btn-secondary w-full">
            <svg class="size-4" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.5-.2-2.2H12v4.3h6.5a5.6 5.6 0 0 1-2.4 3.7v3h3.9c2.3-2.1 3.5-5.2 3.5-8.8z"/><path fill="#34A853" d="M12 24c3.2 0 6-1.1 7.9-2.9l-3.9-3c-1.1.7-2.5 1.2-4 1.2-3.1 0-5.7-2.1-6.6-4.9H1.4v3.1A12 12 0 0 0 12 24z"/><path fill="#FBBC05" d="M5.4 14.4a7.2 7.2 0 0 1 0-4.6V6.7H1.4a12 12 0 0 0 0 10.8l4-3.1z"/><path fill="#EA4335" d="M12 4.8c1.8 0 3.3.6 4.6 1.8l3.4-3.4A11.5 11.5 0 0 0 12 0 12 12 0 0 0 1.4 6.7l4 3.1C6.3 7 8.9 4.8 12 4.8z"/></svg>
            {{ __('auth.login_with_google') }}
        </a>
    @endif
    @if (Route::has('register'))
        <p class="text-center text-sm text-muted">{{ __('auth.no_account') }} <a class="link" href="{{ route('register') }}">{{ __('auth.register') }}</a></p>
    @endif
    @if (config('demo.enabled'))
        <div class="rounded-xl border border-brand-200 bg-brand-50 p-3.5 text-xs text-brand-900 dark:border-brand-800 dark:bg-brand-900/20 dark:text-brand-100">
            <p class="mb-1 font-semibold">{{ __('demo.credentials') }} · <span class="font-mono">{{ config('demo.password') }}</span></p>
            <p class="font-mono">admin@demo.test · owner@bella-italia.demo · manager@bella-italia.demo</p>
        </div>
    @endif
</x-layouts.guest>
