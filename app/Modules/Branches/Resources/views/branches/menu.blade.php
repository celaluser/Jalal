@php($restaurant = auth()->user()->restaurant)
@php($tr = fn ($m) => $m->tr('name', app()->getLocale(), $restaurant->locale))
<x-layouts.app :title="__('branches.menu_title', ['name' => $branch->name])">
    <x-ui.page-header :title="__('branches.menu_title', ['name' => $branch->name])" :description="__('branches.menu_help')" :back="['url' => route('branches.index'), 'label' => __('branches.title')]" />

    @if ($others->isNotEmpty())
        <form method="POST" action="{{ route('branches.menu.copy', $branch->id) }}" class="card card-pad mb-4 flex flex-wrap items-end gap-3">@csrf
            <div class="min-w-48 flex-1"><x-ui.select name="from" :label="__('branches.copy_from')" :options="$others->pluck('name', 'id')->all()" required /></div>
            <x-ui.button variant="secondary" :block="false">{{ __('branches.copy') }}</x-ui.button>
        </form>
    @endif

    <form method="POST" action="{{ route('branches.menu.update', $branch->id) }}" class="space-y-4">@csrf @method('PUT')
        @foreach ($categories as $category)
            @continue($category->products->isEmpty())
            <x-ui.card :title="$tr($category)">
                <div class="divide-y divide-line">
                    <div class="hidden grid-cols-[1fr_8rem_9rem_7rem] gap-3 pb-2 text-xs font-medium uppercase tracking-wide text-muted sm:grid"><span>{{ __('branches.col_dish') }}</span><span>{{ __('branches.col_price') }}</span><span>{{ __('branches.col_available') }}</span><span>{{ __('branches.col_stock') }}</span></div>
                    @foreach ($category->products as $product)
                        @php($row = $rows[$product->id] ?? null)
                        <div class="grid items-center gap-2 py-2.5 sm:grid-cols-[1fr_8rem_9rem_7rem] sm:gap-3">
                            <div class="min-w-0"><p class="truncate font-medium">{{ $tr($product) }}</p><p class="text-xs text-muted tnum">{{ $restaurant->money((float) $product->price) }}</p></div>
                            <input type="number" step="0.01" min="0" name="items[{{ $product->id }}][price]" value="{{ $row?->price }}" placeholder="{{ number_format((float) $product->price, 2, '.', '') }}" class="field" aria-label="{{ __('branches.col_price') }}">
                            <select name="items[{{ $product->id }}][available]" class="field" aria-label="{{ __('branches.col_available') }}">
                                <option value="">{{ __('branches.same') }}</option>
                                <option value="1" @selected($row && $row->is_available === true)>{{ __('branches.available') }}</option>
                                <option value="0" @selected($row && $row->is_available === false)>{{ __('branches.sold_out') }}</option>
                            </select>
                            <input type="number" min="0" name="items[{{ $product->id }}][stock_qty]" value="{{ $row?->stock_qty }}" placeholder="∞" class="field" aria-label="{{ __('branches.col_stock') }}">
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @endforeach
        <div class="flex justify-end"><x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button></div>
    </form>
</x-layouts.app>
