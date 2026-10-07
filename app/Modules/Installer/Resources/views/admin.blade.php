<x-installer::layout :step="$step">
    <h2 class="display text-2xl font-semibold">{{ __('installer.step_admin') }}</h2>
    <p class="mt-1 text-sm text-muted">{{ __('installer.admin_help') }}</p>
    @if ($errors->has('email') && str_starts_with($errors->first('email'), __('installer.install_failed', ['error' => ''])))
        <x-ui.alert type="error" class="mt-5">{{ $errors->first('email') }}</x-ui.alert>
    @endif
    <form method="POST" action="{{ route('install.run') }}" class="mt-5 space-y-4">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2"><x-ui.input name="site_name" :label="__('installer.site_name')" value="QR Menu" required /><x-ui.input name="site_url" type="url" :label="__('installer.site_url')" :value="$url" required /></div>
        <div class="grid gap-4 sm:grid-cols-2"><x-ui.input name="name" :label="__('auth.name')" required /><x-ui.input name="email" type="email" :label="__('auth.email')" required /></div>
        <div class="grid gap-4 sm:grid-cols-2"><x-ui.input name="password" type="password" :label="__('auth.password_label')" required autocomplete="new-password" :hint="__('auth.password_hint')" /><x-ui.input name="password_confirmation" type="password" :label="__('auth.password_confirm')" required autocomplete="new-password" /></div>
        <x-ui.button size="lg" icon="zap">{{ __('installer.install') }}</x-ui.button>
    </form>
</x-installer::layout>
