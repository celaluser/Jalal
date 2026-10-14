<x-layouts.app :title="__('branches.title')">
    <x-ui.page-header :title="__('branches.title')" :description="__('branches.subtitle')">
        <x-slot:actions>
            @if ($canAdd)<a href="{{ route('branches.create') }}" class="btn btn-primary"><x-ui.icon name="plus" size="4" />{{ __('branches.add') }}</a>@endif
        </x-slot:actions>
    </x-ui.page-header>

    @error('limit')<x-ui.alert type="error" class="mb-4">{{ $message }}</x-ui.alert>@enderror
    <p class="mb-3 text-sm text-muted tnum"><bdi>{{ $limit === null ? __('branches.usage_unlimited', ['used' => $branches->count()]) : __('branches.usage', ['used' => $branches->count(), 'max' => $limit]) }}</bdi></p>

    @if ($branches->isEmpty())
        <div class="card"><x-ui.empty icon="store" :title="__('branches.empty_title')" :text="__('branches.empty_text')" /></div>
    @else
        <ul class="grid gap-3">
            @foreach ($branches as $branch)
                <li class="card flex flex-wrap items-center gap-4 p-4">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 font-semibold">{{ $branch->name }}
                            <x-ui.badge :tone="$branch->is_active ? 'success' : 'danger'" dot>{{ $branch->is_active ? __('branches.is_active') : __('branches.inactive') }}</x-ui.badge></p>
                        <p class="truncate text-sm text-muted">{{ collect([$branch->address, $branch->city])->filter()->implode(', ') ?: '—' }} · {{ trans_choice('branches.tables_count', $tables[$branch->id] ?? 0, ['count' => $tables[$branch->id] ?? 0]) }}</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <a href="{{ route('branches.menu', $branch->id) }}" class="btn btn-secondary btn-sm">{{ __('branches.prices_stock') }}</a>
                        <a href="{{ route('branches.edit', $branch->id) }}" class="btn btn-ghost btn-sm" aria-label="{{ __('admin.edit') }}"><x-ui.icon name="pen" size="4" /></a>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
