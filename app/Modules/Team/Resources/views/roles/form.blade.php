<x-layouts.app :title="$role ? __('team.edit_role') : __('team.new_role')">
    <x-ui.page-header :title="$role ? $role->name : __('team.new_role')" :back="['url' => route('team.roles.index'), 'label' => __('team.roles_title')]" />
    <div class="max-w-3xl space-y-5">
        <form method="POST" action="{{ $role ? route('team.roles.update', $role->id) : route('team.roles.store') }}" class="card card-pad space-y-6">
            @csrf @if ($role) @method('PUT') @endif
            @unless ($role)<x-ui.input name="name" :label="__('team.role_name')" required maxlength="60" />@endunless
            <fieldset>
                <legend class="mb-3 text-sm font-medium">{{ __('team.role_permissions') }}</legend>
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach ($groups as $group => $permissions)
                        <div>
                            <p class="mb-1.5 text-sm font-semibold">{{ __('team.perm_group.'.$group) }}</p>
                            <div class="space-y-1.5">
                                @foreach ($permissions as $permission)
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, old('permissions', $selected)))><code class="text-xs">{{ $permission }}</code></label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </fieldset>
            <div class="flex items-center justify-between gap-3">
                <span>@if ($role)<button type="submit" form="delete-role" class="btn btn-ghost text-red-600 dark:text-red-400">{{ __('admin.delete') }}</button>@endif</span>
                <x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button>
            </div>
        </form>
        @if ($role)<form id="delete-role" method="POST" action="{{ route('team.roles.destroy', $role->id) }}" onsubmit="return confirm('{{ __('menu.delete_confirm') }}')">@csrf @method('DELETE')</form>@endif
    </div>
</x-layouts.app>
