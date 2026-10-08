<x-layouts.app :title="__('menu.menus_title')">
    <x-ui.page-header :title="__('menu.menus_title')" :description="__('menu.menus_sub')" :back="['url' => route('menu.index'), 'label' => __('menu.title')]">
        <x-slot:actions><a href="{{ route('menu.menus.create') }}" class="btn btn-primary"><x-ui.icon name="plus" size="4" />{{ __('menu.new_menu') }}</a></x-slot:actions>
    </x-ui.page-header>

    @if ($menus->isEmpty())
        <div class="card"><x-ui.empty icon="layout" :title="__('menu.menus_empty')" :text="__('menu.menus_empty_text')"><a href="{{ route('menu.menus.create') }}" class="btn btn-primary">{{ __('menu.new_menu') }}</a></x-ui.empty></div>
    @else
        <ul class="grid gap-3">
            @foreach ($menus as $m)
                <li class="card flex flex-wrap items-center gap-4 p-4">
                    <div class="min-w-0 flex-1"><p class="font-semibold">{{ $m->tr('name', null, $restaurant->locale) }}</p>
                        <p class="text-sm text-muted">{{ trans_choice('menu.menu_categories', $m->categories_count, ['count' => $m->categories_count]) }}@if ($m->schedule) · {{ \App\Modules\Menu\Support\Schedule::describe($m->schedule, [__('analytics.mon'), __('analytics.tue'), __('analytics.wed'), __('analytics.thu'), __('analytics.fri'), __('analytics.sat'), __('analytics.sun')]) }}@else · {{ __('menu.always_on') }}@endif</p></div>
                    <x-ui.badge :tone="$m->is_active ? 'success' : 'neutral'" dot>{{ $m->is_active ? __('admin.active') : __('admin.inactive') }}</x-ui.badge>
                    <a href="{{ route('menu.menus.edit', $m) }}" class="btn btn-ghost btn-sm" aria-label="{{ __('admin.edit') }}"><x-ui.icon name="pen" size="4" /></a>
                    <form method="POST" action="{{ route('menu.menus.destroy', $m) }}" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" aria-label="{{ __('admin.delete') }}"><x-ui.icon name="trash" size="4" /></button></form>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
