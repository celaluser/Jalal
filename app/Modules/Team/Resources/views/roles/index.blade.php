<x-layouts.app :title="__('team.roles_title')">
    <x-ui.page-header :title="__('team.roles_title')" :description="__('team.roles_sub')" :back="['url' => route('team.index'), 'label' => __('team.title')]">
        <x-slot:actions><a href="{{ route('team.roles.create') }}" class="btn btn-primary"><x-ui.icon name="plus" size="4" />{{ __('team.new_role') }}</a></x-slot:actions>
    </x-ui.page-header>
    @error('role')<x-ui.alert type="error" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted">{{ __('team.custom_roles') }}</h2>
    <div class="mb-8 grid gap-3 sm:grid-cols-2">
        @forelse ($custom as $role)
            <a href="{{ route('team.roles.edit', $role->id) }}" class="card p-4 transition hover:border-line-strong hover:shadow-pop">
                <p class="font-semibold">{{ $role->name }}</p>
                <p class="mt-0.5 text-sm text-muted">{{ trans_choice('team.role_users', (int) ($counts[$role->id] ?? 0)) }} · {{ $role->permissions->count() }} {{ __('team.role_permissions') }}</p>
            </a>
        @empty
            <p class="text-sm text-muted">{{ __('team.no_custom') }}</p>
        @endforelse
    </div>

    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted">{{ __('team.built_in') }}</h2>
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach ($builtIn as $role)
            <div class="card p-4">
                <p class="font-semibold">{{ __('team.role_'.$role) }}</p>
                <p class="mt-0.5 text-sm text-muted">{{ __('team.role_'.$role.'_text') }}</p>
                <p class="mt-2 flex flex-wrap gap-1.5">@foreach ($defaults[$role] as $p)<x-ui.badge>{{ $p }}</x-ui.badge>@endforeach</p>
            </div>
        @endforeach
    </div>
</x-layouts.app>
