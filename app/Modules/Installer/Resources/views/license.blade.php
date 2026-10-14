<x-installer::layout :step="$step">
    <h2 class="display text-2xl font-semibold">{{ __('installer.step_license') }}</h2>
    <p class="mt-1 text-sm text-muted">{{ __('installer.license_help') }}</p>
    @if ($driver === 'format')<x-ui.alert type="warning" class="mt-5">{{ __('installer.license_dev_mode') }}</x-ui.alert>@endif
    <form method="POST" action="{{ route('install.license') }}" class="mt-5 space-y-5">
        @csrf
        <x-ui.input name="purchase_code" :label="__('installer.purchase_code')" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" required autofocus class="font-mono" />
        <x-ui.button size="lg">{{ __('installer.next') }}</x-ui.button>
    </form>
</x-installer::layout>
