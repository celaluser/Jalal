<x-layouts.app :title="__('tables.title')">
    <x-ui.page-header :title="__('tables.title')" :description="__('tables.subtitle')">
        <x-slot:actions>
            @can('tables.manage')<a href="{{ route('tables.qr') }}" class="btn btn-primary"><x-ui.icon name="qr" size="4" />{{ __('tables.print_qr') }}</a>@endcan
        </x-slot:actions>
    </x-ui.page-header>

    @error('limit')<x-ui.alert type="warning" class="mb-4"><span class="flex flex-wrap items-center justify-between gap-2"><span>{{ $message }}</span><a class="font-semibold underline" href="{{ route('billing.index') }}">{{ __('menu.upgrade') }}</a></span></x-ui.alert>@enderror

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section aria-label="{{ __('tables.tables') }}" class="min-w-0">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <nav class="flex flex-wrap gap-1.5" aria-label="{{ __('tables.areas') }}">
                    @php($chips = ['' => __('tables.all_areas')] + $areas->mapWithKeys(fn ($a) => [$a->id => $a->name])->all() + ($areas->isNotEmpty() ? ['none' => __('tables.no_area')] : []))
                    @foreach ($chips as $key => $label)
                        <a href="{{ route('tables.index', $key === '' ? [] : ['area' => $key]) }}" @class(['rounded-full border px-3 py-1 text-sm transition', 'border-ink-950 bg-ink-950 text-white dark:border-white dark:bg-white dark:text-ink-950' => (string) $areaFilter === (string) $key, 'border-line-strong text-muted hover:bg-surface-2' => (string) $areaFilter !== (string) $key]) @if ((string) $areaFilter === (string) $key) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                <span class="tnum text-sm text-muted"><bdi>{{ $limit === null ? __('tables.usage_unlimited', ['used' => $total]) : __('tables.usage', ['used' => $total, 'max' => $limit]) }}</bdi></span>
            </div>

            @if ($tables->isEmpty())
                <div class="card"><x-ui.empty icon="qr" :title="__('tables.empty_title')" :text="__('tables.empty_text')" /></div>
            @else
                <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($tables as $table)
                        <li class="card flex flex-col p-4">
                            <div class="flex items-start gap-3">
                                <div class="size-20 shrink-0 overflow-hidden rounded-xl ring-1 ring-line [&>svg]:size-full" aria-hidden="true">{!! $qr->svg($restaurant, $table, $style) !!}</div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="display truncate text-lg font-semibold">{{ $table->name }}</h3>
                                    <p class="mt-0.5 text-sm text-muted">{{ $table->area?->name ?? __('tables.no_area') }}@if ($table->seats) · {{ __('tables.seats_count', ['count' => $table->seats]) }}@endif</p>
                                    @unless ($table->is_active)<x-ui.badge class="mt-1.5">{{ __('tables.inactive') }}</x-ui.badge>@endunless
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap items-center gap-x-1 border-t border-line pt-3">
                                <a class="btn btn-ghost btn-sm" href="{{ route('tables.qr.download', [$table->id, 'png']) }}"><x-ui.icon name="download" size="4" />{{ __('tables.download_png') }}</a>
                                <a class="btn btn-ghost btn-sm" href="{{ route('tables.qr.download', [$table->id, 'svg']) }}">{{ __('tables.download_svg') }}</a>
                                @can('tables.manage')
                                    <a class="btn btn-ghost btn-sm ms-auto" href="{{ route('tables.edit', $table->id) }}" aria-label="{{ __('admin.edit') }}"><x-ui.icon name="pen" size="4" /></a>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @can('tables.manage')
            <aside class="space-y-5">
                <x-ui.card :title="__('tables.add_table')">
                    <form method="POST" action="{{ route('tables.store') }}" class="space-y-3">@csrf
                        <x-ui.input name="name" :label="__('tables.name')" required maxlength="60" />
                        <div class="grid grid-cols-2 gap-3">
                            <x-ui.select name="area_id" :label="__('tables.area')" :options="$areas->pluck('name', 'id')->all()" placeholder="—" />
                            <x-ui.input name="seats" type="number" min="1" max="99" :label="__('tables.seats')" />
                        </div>
                        <x-ui.button size="sm" icon="plus" :disabled="$remaining === 0">{{ __('tables.add_table') }}</x-ui.button>
                    </form>
                </x-ui.card>

                <x-ui.card :title="__('tables.add_several')" :description="__('tables.bulk_help')">
                    <form method="POST" action="{{ route('tables.bulk') }}" class="space-y-3">@csrf
                        <x-ui.input name="prefix" :label="__('tables.prefix')" :value="__('tables.default_prefix')" maxlength="30" />
                        <div class="grid grid-cols-2 gap-3">
                            <x-ui.input name="from" type="number" min="1" :label="__('tables.from')" value="1" required />
                            <x-ui.input name="to" type="number" min="1" :label="__('tables.to')" value="10" required />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <x-ui.select name="area_id" :label="__('tables.area')" :options="$areas->pluck('name', 'id')->all()" placeholder="—" />
                            <x-ui.input name="seats" type="number" min="1" max="99" :label="__('tables.seats')" />
                        </div>
                        <x-ui.button variant="secondary" size="sm" :disabled="$remaining === 0">{{ __('tables.create') }}</x-ui.button>
                    </form>
                </x-ui.card>

                <x-ui.card :title="__('tables.areas')" :description="__('tables.areas_hint')">
                    <ul class="mb-4 space-y-2">
                        @foreach ($areas as $area)
                            <li class="flex items-center gap-2">
                                <form method="POST" action="{{ route('tables.areas.update', $area->id) }}" class="flex min-w-0 flex-1 gap-1">@csrf @method('PUT')
                                    <input name="name" value="{{ $area->name }}" class="field !py-1.5" maxlength="80" aria-label="{{ __('tables.area_name') }}">
                                    <button class="btn btn-ghost btn-sm" aria-label="{{ __('admin.save') }}"><x-ui.icon name="check" size="4" /></button>
                                </form>
                                <form method="POST" action="{{ route('tables.areas.destroy', $area->id) }}" onsubmit="return confirm('{{ __('menu.delete_confirm') }}')">@csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm text-red-600 dark:text-red-400" aria-label="{{ __('admin.delete') }}"><x-ui.icon name="trash" size="4" /></button></form>
                            </li>
                        @endforeach
                    </ul>
                    <form method="POST" action="{{ route('tables.areas.store') }}" class="flex gap-2">@csrf
                        <input name="name" class="field" maxlength="80" placeholder="{{ __('tables.area_name') }}" aria-label="{{ __('tables.area_name') }}" required>
                        <button class="btn btn-secondary" aria-label="{{ __('tables.add_area') }}"><x-ui.icon name="plus" size="4" /></button>
                    </form>
                    @error('name')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                </x-ui.card>
            </aside>
        @endcan
    </div>
</x-layouts.app>
