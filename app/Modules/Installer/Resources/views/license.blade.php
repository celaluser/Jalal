<x-installer::layout :step="$step">
    <h2 class="mb-1 text-lg font-semibold">{{ __('installer.step_license') }}</h2>
    <p class="mb-4 text-sm text-gray-500">{{ __('installer.license_help') }}</p>
    @if ($driver === 'format')<x-ui.alert type="error">{{ __('installer.license_dev_mode') }}</x-ui.alert>@endif
    <form method="POST" action="{{ route('install.license') }}" class="space-y-4">
        @csrf
        <x-ui.input name="purchase_code" :label="__('installer.purchase_code')" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" required autofocus />
        <x-ui.button>{{ __('installer.next') }}</x-ui.button>
    </form>
</x-installer::layout>
