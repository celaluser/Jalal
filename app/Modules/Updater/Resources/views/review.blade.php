<x-layouts.admin :title="__('updater.title')">
    <x-ui.card class="mx-auto max-w-xl">
        <h1 class="mb-1 text-xl font-semibold">{{ __('updater.review') }}</h1>
        <p class="mb-3 text-sm text-gray-500">{{ $current }} → <strong>{{ $package->version() }}</strong> · {{ __('updater.file_count', ['count' => count($package->paths())]) }}</p>
        @if ($package->notes())<pre class="mb-4 whitespace-pre-wrap rounded-lg bg-gray-100 p-3 text-sm dark:bg-gray-800">{{ $package->notes() }}</pre>@endif
        <x-ui.alert type="error">{{ __('updater.backup_warning') }}</x-ui.alert>
        <form method="POST" action="{{ route('admin.updates.apply') }}" class="space-y-3">
            @csrf
            <x-ui.input name="password" type="password" :label="__('updater.confirm_password')" required autofocus />
            <x-ui.button>{{ __('updater.apply') }}</x-ui.button>
        </form>
        <form method="POST" action="{{ route('admin.updates.cancel') }}" class="mt-3">@csrf @method('DELETE')
            <x-ui.button variant="secondary">{{ __('updater.cancel') }}</x-ui.button>
        </form>
    </x-ui.card>
</x-layouts.admin>
