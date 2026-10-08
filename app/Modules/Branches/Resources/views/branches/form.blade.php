@php($editing = $branch->exists)
<x-layouts.app :title="$editing ? __('branches.edit') : __('branches.add')">
    <x-ui.page-header :title="$editing ? __('branches.edit') : __('branches.add')" :back="['url' => route('branches.index'), 'label' => __('branches.title')]" />
    <div class="max-w-xl space-y-5">
        @error('limit')<x-ui.alert type="error">{{ $message }}</x-ui.alert>@enderror
        <form method="POST" action="{{ $editing ? route('branches.update', $branch->id) : route('branches.store') }}" class="card card-pad space-y-4">
            @csrf @if ($editing) @method('PUT') @endif
            <x-ui.input name="name" :label="__('branches.name')" :value="$branch->name" required maxlength="120" />
            <x-ui.input name="address" :label="__('branches.address')" :value="$branch->address" maxlength="255" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="city" :label="__('branches.city')" :value="$branch->city" maxlength="100" />
                <x-ui.input name="phone" type="tel" :label="__('branches.phone')" :value="$branch->phone" maxlength="40" />
            </div>
            @include('menu::partials.schedule', ['schedule' => $branch->schedule ?? [], 'label' => __('branches.hours')])
            <p class="-mt-2 text-xs text-muted">{{ __('branches.hours_help') }}</p>
            <x-ui.checkbox name="is_active" :label="__('branches.is_active')" :checked="old('is_active', $branch->is_active)" />
            <div class="flex items-center justify-between gap-3 pt-2">
                <span>@if ($editing)<button type="submit" form="delete-branch" class="btn btn-ghost text-red-600 dark:text-red-400">{{ __('admin.delete') }}</button>@endif</span>
                <x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button>
            </div>
        </form>
        @if ($editing)
            <form id="delete-branch" method="POST" action="{{ route('branches.destroy', $branch->id) }}" onsubmit="return confirm('{{ __('menu.delete_confirm') }}')">@csrf @method('DELETE')</form>
        @endif
    </div>
</x-layouts.app>
