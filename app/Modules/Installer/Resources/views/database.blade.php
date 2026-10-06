<x-installer::layout :step="$step">
    <h2 class="mb-1 text-lg font-semibold">{{ __('installer.step_database') }}</h2>
    <p class="mb-4 text-sm text-gray-500">{{ __('installer.database_help') }}</p>
    @error('database')<x-ui.alert type="error">{{ $message }}</x-ui.alert>@enderror
    <form method="POST" action="{{ route('install.database') }}" class="space-y-4" x-data="{ driver: '{{ old('driver', 'mysql') }}' }">
        @csrf
        <div>
            <label for="driver" class="mb-1 block text-sm font-medium">{{ __('installer.driver') }}</label>
            <select id="driver" name="driver" x-model="driver" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950">
                <option value="mysql">MySQL / MariaDB</option>
                <option value="sqlite">SQLite ({{ __('installer.dev_only') }})</option>
            </select>
        </div>
        <div x-show="driver === 'mysql'" class="space-y-4">
            <x-ui.input name="host" :label="__('installer.db_host')" value="127.0.0.1" />
            <x-ui.input name="port" :label="__('installer.db_port')" type="number" placeholder="3306" />
            <x-ui.input name="username" :label="__('installer.db_username')" autocomplete="off" />
            <x-ui.input name="password" type="password" :label="__('installer.db_password')" autocomplete="off" />
        </div>
        <x-ui.input name="database" :label="__('installer.db_name')" required />
        <x-ui.button>{{ __('installer.test_and_continue') }}</x-ui.button>
    </form>
</x-installer::layout>
