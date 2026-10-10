<x-installer::layout :step="$step">
    <h2 class="display text-2xl font-semibold">{{ __('installer.step_database') }}</h2>
    <p class="mt-1 text-sm text-muted">{{ __('installer.database_help') }}</p>
    @error('database')<x-ui.alert type="error" class="mt-5">{{ $message }}</x-ui.alert>@enderror
    <form method="POST" action="{{ route('install.database') }}" class="mt-5 space-y-4" x-data="{ driver: '{{ old('driver', 'mysql') }}' }">
        @csrf
        <x-ui.select name="driver" :label="__('installer.driver')" :options="['mysql' => 'MySQL / MariaDB', 'sqlite' => 'SQLite ('.__('installer.dev_only').')']" x-model="driver" />
        <div x-show="driver === 'mysql'" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-3"><div class="sm:col-span-2"><x-ui.input name="host" :label="__('installer.db_host')" value="127.0.0.1" /></div><x-ui.input name="port" :label="__('installer.db_port')" type="number" placeholder="3306" /></div>
            <div class="grid gap-4 sm:grid-cols-2"><x-ui.input name="username" :label="__('installer.db_username')" autocomplete="off" /><x-ui.input name="password" type="password" :label="__('installer.db_password')" autocomplete="off" /></div>
        </div>
        <x-ui.input name="database" :label="__('installer.db_name')" required />
        <x-ui.button size="lg" icon="check">{{ __('installer.test_and_continue') }}</x-ui.button>
    </form>
</x-installer::layout>
