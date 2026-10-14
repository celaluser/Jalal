<x-layouts.app :title="__('menu.stock_title')">
    <x-ui.page-header :title="__('menu.stock_title')" :description="__('menu.stock_sub')" :back="['url' => route('menu.index'), 'label' => __('menu.title')]">
        <x-slot:actions><a href="{{ route('menu.import') }}" class="btn btn-secondary"><x-ui.icon name="upload" size="4" />{{ __('menu.import_title') }}</a>
            <a href="{{ route('menu.export') }}" class="btn btn-secondary"><x-ui.icon name="download" size="4" />{{ __('menu.export_csv') }}</a></x-slot:actions>
    </x-ui.page-header>

    <div class="grid items-start gap-5 xl:grid-cols-[1fr_20rem]">
        <form method="POST" action="{{ route('menu.stock.update') }}" class="space-y-5">
            @csrf @method('PUT')
            <p class="text-sm text-muted">{{ __('menu.stock_tip') }}</p>
            @foreach ($categories as $category)
                @continue($category->products->isEmpty())
                <x-ui.card :title="$category->tr('name', null, $restaurant->locale)" :pad="false">
                    <div class="overflow-x-auto">
                        <table class="table min-w-[640px]">
                            <thead><tr><th>{{ __('menu.stock_col_dish') }}</th><th class="w-32">{{ __('menu.stock_col_price') }} ({{ $restaurant->currency_code }})</th><th class="w-28">{{ __('menu.stock_col_stock') }}</th><th class="w-24">{{ __('menu.stock_col_warn') }}</th><th class="w-24 text-center">{{ __('menu.stock_col_on') }}</th></tr></thead>
                            <tbody>
                                @foreach ($category->products as $p)
                                    <tr>
                                        <td><span class="font-medium {{ $p->is_active ? '' : 'text-muted' }}">{{ $p->tr('name', null, $restaurant->locale) }}</span>
                                            @if (! $p->inStock())<x-ui.badge tone="danger" class="ms-2">{{ __('menu.out_of_stock') }}</x-ui.badge>@elseif ($p->isLowStock())<x-ui.badge tone="warning" class="ms-2">{{ __('menu.low_stock', ['count' => $p->stock_qty]) }}</x-ui.badge>@endif</td>
                                        <td><input type="number" step="0.01" min="0" name="rows[{{ $p->id }}][price]" value="{{ old("rows.{$p->id}.price", $p->price) }}" class="field tnum !py-1.5" aria-label="{{ __('menu.stock_col_price') }}" required></td>
                                        <td><input type="number" min="0" name="rows[{{ $p->id }}][stock_qty]" value="{{ old("rows.{$p->id}.stock_qty", $p->stock_qty) }}" placeholder="∞" class="field tnum !py-1.5" aria-label="{{ __('menu.stock_col_stock') }}"></td>
                                        <td><input type="number" min="0" name="rows[{{ $p->id }}][low_stock_at]" value="{{ old("rows.{$p->id}.low_stock_at", $p->low_stock_at) }}" class="field tnum !py-1.5" aria-label="{{ __('menu.stock_col_warn') }}"></td>
                                        <td class="text-center"><input type="checkbox" name="rows[{{ $p->id }}][is_available]" value="1" @checked(old("rows.{$p->id}.is_available", $p->is_available)) class="size-4 accent-[var(--color-accent-600)]" aria-label="{{ __('menu.stock_col_on') }}"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            @endforeach
            <div class="sticky bottom-3 z-10 flex justify-end"><x-ui.button :block="false" size="lg" class="shadow-lg">{{ __('admin.save') }}</x-ui.button></div>
        </form>

        <x-ui.card :title="__('menu.adjust_title')" :description="__('menu.adjust_sub')" class="xl:sticky xl:top-20">
            <form method="POST" action="{{ route('menu.stock.adjust') }}" class="space-y-4" onsubmit="return confirm('{{ __('menu.adjust_confirm') }}')">
                @csrf
                <x-ui.input name="percent" type="number" step="0.1" min="-90" max="500" :label="__('menu.adjust_percent')" :hint="__('menu.adjust_percent_hint')" required />
                <div>
                    <label for="category_id" class="mb-1.5 block text-sm font-medium">{{ __('menu.adjust_scope') }}</label>
                    <select id="category_id" name="category_id" class="field"><option value="">{{ __('menu.adjust_all') }}</option>
                        @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->tr('name', null, $restaurant->locale) }}</option>@endforeach</select>
                </div>
                <x-ui.button variant="secondary">{{ __('menu.adjust_apply') }}</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>
