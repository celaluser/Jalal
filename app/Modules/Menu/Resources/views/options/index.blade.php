<x-layouts.app :title="__('menu.groups_title')">
    <x-ui.page-header :title="__('menu.groups_title')" :description="__('menu.groups_subtitle')">
        <x-slot:actions><a href="{{ route('menu.option-groups.create') }}" class="btn btn-primary"><x-ui.icon name="plus" size="4" />{{ __('menu.add_group') }}</a></x-slot:actions>
    </x-ui.page-header>

    @if ($groups->isEmpty())
        <div class="card"><x-ui.empty icon="sliders" :title="__('menu.groups_empty_title')" :text="__('menu.groups_empty_text')"><a href="{{ route('menu.option-groups.create') }}" class="btn btn-primary">{{ __('menu.add_group') }}</a></x-ui.empty></div>
    @else
        <ul x-data="sortable(@js(route('menu.reorder', 'option-groups')))" class="grid gap-3">
            @foreach ($groups as $group)
                <li data-id="{{ $group->id }}" class="card flex items-center gap-3 p-4 ps-2">
                    <button type="button" data-handle class="grid size-8 shrink-0 cursor-grab place-items-center rounded-lg text-muted hover:bg-surface-2 active:cursor-grabbing" aria-label="{{ __('menu.drag') }}"><x-ui.icon name="menu" size="4" /></button>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('menu.option-groups.edit', $group) }}" class="font-semibold hover:underline">{{ $group->tr('name', null, auth()->user()->restaurant->locale) }}</a>
                            <x-ui.badge>{{ __('menu.type_'.$group->type) }}</x-ui.badge>
                            @if ($group->is_required)<x-ui.badge tone="warning">{{ __('menu.is_required') }}</x-ui.badge>@endif
                        </div>
                        <p class="mt-1 truncate text-sm text-muted">{{ $group->options->map(fn ($o) => $o->tr('name', null, auth()->user()->restaurant->locale))->implode(' · ') }}</p>
                        <p class="mt-0.5 text-xs text-muted">{{ trans_choice('menu.used_on', $group->products_count) }}</p>
                    </div>
                    <a href="{{ route('menu.option-groups.edit', $group) }}" class="btn btn-ghost btn-sm"><x-ui.icon name="pen" size="4" />{{ __('admin.edit') }}</a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
