<x-layouts.app :title="$menu->exists ? __('menu.edit_menu') : __('menu.new_menu')">
    <x-ui.page-header :title="$menu->exists ? __('menu.edit_menu') : __('menu.new_menu')" :back="['url' => route('menu.menus.index'), 'label' => __('menu.menus_title')]" />
    <form method="POST" action="{{ $menu->exists ? route('menu.menus.update', $menu) : route('menu.menus.store') }}" class="card card-pad max-w-2xl space-y-5">
        @csrf @if ($menu->exists) @method('PUT') @endif
        <x-ui.translatable name="name" :label="__('menu.name')" :locales="$locales" :values="$menu->name ?? []" required :maxlength="80" />
        @include('menu::partials.schedule', ['schedule' => $menu->schedule, 'label' => __('menu.menu_hours')])
        <fieldset>
            <legend class="mb-1 text-sm font-medium">{{ __('menu.menu_categories_pick') }}</legend>
            <p class="mb-2 text-xs text-muted">{{ __('menu.menu_categories_help') }}</p>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($categories as $c)
                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-line-strong p-3 text-sm has-[:checked]:border-accent-600 has-[:checked]:bg-accent-50 dark:has-[:checked]:bg-accent-900/20">
                        <input type="checkbox" name="categories[]" value="{{ $c->id }}" class="check" @checked(in_array($c->id, old('categories', $menu->exists ? $c->menu_id === $menu->id ? [$c->id] : [] : [])))> {{ $c->icon }} {{ $c->tr('name', null, $restaurant->locale) }}</label>
                @endforeach
            </div>
        </fieldset>
        <x-ui.checkbox name="is_active" :label="__('menu.is_active')" :checked="$menu->is_active" />
        <div class="flex gap-3"><x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button><a href="{{ route('menu.menus.index') }}" class="btn btn-secondary">{{ __('admin.cancel') }}</a></div>
    </form>
</x-layouts.app>
