<x-installer::layout :step="$step">
    <h2 class="mb-4 text-lg font-semibold">{{ __('installer.step_admin') }}</h2>
    @if ($errors->has('email') && str_starts_with($errors->first('email'), __('installer.install_failed', ['error' => ''])))
        <x-ui.alert type="error">{{ $errors->first('email') }}</x-ui.alert>
    @endif
    <form method="POST" action="{{ route('install.run') }}" class="space-y-4">
        @csrf
        <x-ui.input name="site_name" :label="__('installer.site_name')" value="QR Menu" required />
        <x-ui.input name="site_url" type="url" :label="__('installer.site_url')" :value="$url" required />
        <x-ui.input name="name" :label="__('auth.name')" required />
        <x-ui.input name="email" type="email" :label="__('auth.email')" required />
        <x-ui.input name="password" type="password" :label="__('auth.password_label')" required autocomplete="new-password" />
        <x-ui.input name="password_confirmation" type="password" :label="__('auth.password_confirm')" required autocomplete="new-password" />
        <x-ui.button>{{ __('installer.install') }}</x-ui.button>
    </form>
</x-installer::layout>
