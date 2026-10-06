<x-layouts.admin :title="__('updater.title')">
    <div class="space-y-6">
        <x-ui.card>
            <h1 class="mb-1 text-xl font-semibold">{{ __('updater.title') }}</h1>
            <p class="text-sm text-gray-500">{{ __('updater.current_version', ['version' => $current]) }}</p>
        </x-ui.card>
        @if (session('status'))<x-ui.alert>{{ session('status') }}</x-ui.alert>@endif
        <x-ui.card>
            <h2 class="mb-3 font-semibold">{{ __('updater.upload') }}</h2>
            <p class="mb-3 text-sm text-gray-500">{{ __('updater.upload_help') }}</p>
            <form method="POST" action="{{ route('admin.updates.upload') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <input type="file" name="package" accept=".zip" required class="block w-full text-sm">
                @error('package')<p class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                <x-ui.button>{{ __('updater.check_package') }}</x-ui.button>
            </form>
        </x-ui.card>
        <x-ui.card>
            <h2 class="mb-3 font-semibold">{{ __('updater.history') }}</h2>
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500"><tr><th>{{ __('updater.version') }}</th><th>{{ __('updater.status') }}</th><th>{{ __('updater.date') }}</th></tr></thead>
                <tbody>
                    @forelse ($history as $row)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="py-1.5">{{ $row->from_version }} → {{ $row->version }}</td>
                            <td>{{ $row->status }}</td>
                            <td>{{ $row->created_at->toDayDateTimeString() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-2 text-gray-500">{{ __('updater.no_history') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.card>
    </div>
</x-layouts.admin>
