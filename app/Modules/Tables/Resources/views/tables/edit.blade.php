<x-layouts.app :title="__('tables.edit_table')">
    <x-ui.page-header :title="__('tables.edit_table')" :back="['url' => route('tables.index'), 'label' => __('tables.title')]" />
    <div class="max-w-xl space-y-5">
        <form method="POST" action="{{ route('tables.update', $table->id) }}" class="card card-pad space-y-4">@csrf @method('PUT')
            <x-ui.input name="name" :label="__('tables.name')" :value="$table->name" required maxlength="60" />
            <div class="grid grid-cols-2 gap-4">
                <x-ui.select name="area_id" :label="__('tables.area')" :options="$areas->pluck('name', 'id')->all()" :value="$table->area_id" placeholder="—" />
                <x-ui.input name="seats" type="number" min="1" max="99" :label="__('tables.seats')" :value="$table->seats" />
            </div>
            <x-ui.checkbox name="is_active" :label="__('tables.is_active')" :checked="$table->is_active" />
            <div class="flex items-center justify-between gap-3 pt-2">
                <button type="submit" form="delete-table" class="btn btn-ghost text-red-600 dark:text-red-400">{{ __('admin.delete') }}</button>
                <x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button>
            </div>
        </form>
        <form id="delete-table" method="POST" action="{{ route('tables.destroy', $table->id) }}" onsubmit="return confirm('{{ __('menu.delete_confirm') }}')">@csrf @method('DELETE')</form>

        <x-ui.card :title="__('tables.regenerate')">
            <p class="mb-3 text-sm text-muted">{{ __('tables.regenerate_confirm') }}</p>
            <form method="POST" action="{{ route('tables.regenerate', $table->id) }}" onsubmit="return confirm('{{ __('tables.regenerate_confirm') }}')">@csrf
                <x-ui.button variant="secondary" size="sm" :block="false" icon="refresh">{{ __('tables.regenerate') }}</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>
