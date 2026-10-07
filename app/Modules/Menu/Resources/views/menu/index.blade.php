<x-layouts.app :title="__('menu.title')">
    <x-ui.page-header :title="__('menu.title')" :description="__('menu.subtitle')">
        <x-slot:actions>
            <a href="{{ route('menu.categories.create') }}" class="btn btn-secondary"><x-ui.icon name="plus" size="4" />{{ __('menu.add_category') }}</a>
            @if ($categories->isNotEmpty())<a href="{{ route('menu.products.create', ['category' => $current?->id]) }}" class="btn btn-primary"><x-ui.icon name="plus" size="4" />{{ __('menu.add_product') }}</a>@endif
        </x-slot:actions>
    </x-ui.page-header>

    @error('limit')<x-ui.alert type="warning" class="mb-4"><span class="flex flex-wrap items-center justify-between gap-2"><span>{{ $message }}</span><a class="font-semibold underline" href="{{ route('billing.index') }}">{{ __('menu.upgrade') }}</a></span></x-ui.alert>@enderror

    @if ($categories->isEmpty())
        <div class="card"><x-ui.empty icon="store" :title="__('menu.no_categories_title')" :text="__('menu.no_categories_text')"><a href="{{ route('menu.categories.create') }}" class="btn btn-primary">{{ __('menu.add_category') }}</a></x-ui.empty></div>
    @else
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[18rem_minmax(0,1fr)]">
            {{-- Categories --}}
            <aside aria-label="{{ __('menu.categories') }}">
                <div class="card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-line px-4 py-3">
                        <h2 class="text-sm font-semibold">{{ __('menu.categories') }}</h2>
                        <span class="tnum text-xs text-muted"><bdi>{{ $categories->count() }}@if ($categoryLimit !== null) / {{ $categoryLimit }}@endif</bdi></span>
                    </div>
                    <ul x-data="sortable(@js(route('menu.reorder', 'categories')))" class="divide-y divide-line">
                        @foreach ($categories as $category)
                            <li data-id="{{ $category->id }}" x-ref="row{{ $category->id }}" @class(['group flex items-center gap-1 ps-1 pe-2', 'bg-surface-2' => $current?->is($category)])>
                                <button type="button" data-handle class="grid size-8 shrink-0 cursor-grab place-items-center rounded-lg text-muted hover:bg-surface-2 active:cursor-grabbing" aria-label="{{ __('menu.drag') }}"><x-ui.icon name="menu" size="4" /></button>
                                <a href="{{ route('menu.index', ['category' => $category->id]) }}" class="flex min-w-0 flex-1 items-center justify-between gap-2 py-3 text-sm" @if ($current?->is($category)) aria-current="true" @endif>
                                    <span class="truncate font-medium {{ $category->is_active ? '' : 'text-muted line-through' }}">{{ $category->tr('name', null, $restaurant->locale) }}</span>
                                    <span class="tnum shrink-0 rounded-full bg-surface-2 px-2 py-0.5 text-xs text-muted">{{ $category->products_count }}</span>
                                </a>
                                <span class="flex shrink-0 opacity-0 transition focus-within:opacity-100 group-hover:opacity-100">
                                    <button type="button" class="grid size-7 place-items-center rounded text-muted hover:text-fg" aria-label="{{ __('menu.move_up') }}" x-on:click="move($refs.row{{ $category->id }}, -1)"><x-ui.icon name="chevron-down" size="4" class="rotate-180" /></button>
                                    <button type="button" class="grid size-7 place-items-center rounded text-muted hover:text-fg" aria-label="{{ __('menu.move_down') }}" x-on:click="move($refs.row{{ $category->id }}, 1)"><x-ui.icon name="chevron-down" size="4" /></button>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </aside>

            {{-- Products of the selected category --}}
            <section aria-label="{{ __('menu.products') }}">
                @if ($current)
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="display truncate text-xl font-semibold">{{ $current->tr('name', null, $restaurant->locale) }}</h2>
                            <p class="text-sm text-muted">{{ trans_choice('menu.products_count', $products->count()) }}@if ($productLimit !== null) · <span class="tnum"><bdi>{{ __('menu.usage_products', ['used' => $productCount, 'max' => $productLimit]) }}</bdi></span>@endif</p>
                        </div>
                        <a href="{{ route('menu.categories.edit', $current) }}" class="btn btn-ghost btn-sm"><x-ui.icon name="pen" size="4" />{{ __('menu.edit_category') }}</a>
                    </div>

                    @if ($products->isNotEmpty())
                        <p class="mb-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                            <span class="inline-flex items-center gap-1"><x-ui.icon name="zap" size="4" />{{ __('menu.is_available') }}</span>
                            <span class="inline-flex items-center gap-1"><x-ui.icon name="sparkles" size="4" />{{ __('menu.is_featured') }}</span>
                            <span class="inline-flex items-center gap-1"><x-ui.icon name="eye" size="4" />{{ __('menu.is_active') }}</span>
                        </p>
                    @endif
                    <div class="card overflow-hidden">
                        @if ($products->isEmpty())
                            <x-ui.empty icon="inbox" :title="__('menu.no_products_title')" :text="__('menu.no_products_text')"><a href="{{ route('menu.products.create', ['category' => $current->id]) }}" class="btn btn-primary">{{ __('menu.add_product') }}</a></x-ui.empty>
                        @else
                            <ul x-data="sortable(@js(route('menu.reorder', 'products')))" class="divide-y divide-line">
                                @foreach ($products as $product)
                                    <li data-id="{{ $product->id }}" x-ref="p{{ $product->id }}" class="flex items-center gap-3 p-3 ps-2">
                                        <button type="button" data-handle class="grid size-8 shrink-0 cursor-grab place-items-center rounded-lg text-muted hover:bg-surface-2 active:cursor-grabbing" aria-label="{{ __('menu.drag') }}"><x-ui.icon name="menu" size="4" /></button>
                                        <div class="size-14 shrink-0 overflow-hidden rounded-xl bg-surface-2 ring-1 ring-line">
                                            <img src="{{ $product->pictureUrl() }}" alt="" class="size-full object-cover" loading="lazy">
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <a href="{{ route('menu.products.edit', $product) }}" class="block truncate font-semibold hover:underline {{ $product->is_active ? '' : 'text-muted' }}">{{ $product->tr('name', null, $restaurant->locale) }}</a>
                                            <p class="tnum mt-0.5 text-sm"><bdi class="font-medium">{{ $restaurant->money($product->price) }}</bdi>@if ($product->isOnSale()) <bdi class="text-muted line-through">{{ $restaurant->money($product->compare_price) }}</bdi>@endif</p>
                                            <div class="mt-1 flex flex-wrap gap-1.5">
                                                @unless ($product->is_active)<x-ui.badge>{{ __('menu.hidden') }}</x-ui.badge>@endunless
                                                @unless ($product->is_available)<x-ui.badge tone="danger">{{ __('menu.sold_out') }}</x-ui.badge>@endunless
                                                @if ($product->is_featured)<x-ui.badge tone="warning">{{ __('menu.featured') }}</x-ui.badge>@endif
                                            </div>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-0.5">
                                            @foreach ([['is_available', 'zap', 'toggle_available'], ['is_featured', 'sparkles', 'toggle_featured'], ['is_active', 'eye', 'toggle_active']] as [$field, $icon, $label])
                                                <form method="POST" action="{{ route('menu.products.toggle', $product) }}">@csrf <input type="hidden" name="field" value="{{ $field }}">
                                                    <button class="grid size-9 place-items-center rounded-lg transition hover:bg-surface-2 {{ $product->{$field} ? 'text-accent-700 dark:text-accent-300' : 'text-ink-400' }}" aria-label="{{ __('menu.'.$label) }}" aria-pressed="{{ $product->{$field} ? 'true' : 'false' }}" title="{{ __('menu.'.$label) }}"><x-ui.icon :name="$icon" size="5" /></button>
                                                </form>
                                            @endforeach
                                            <span class="mx-1 hidden h-5 w-px bg-line sm:block"></span>
                                            <span class="hidden sm:flex">
                                                <button type="button" class="grid size-8 place-items-center rounded text-muted hover:text-fg" aria-label="{{ __('menu.move_up') }}" x-on:click="move($refs.p{{ $product->id }}, -1)"><x-ui.icon name="chevron-down" size="4" class="rotate-180" /></button>
                                                <button type="button" class="grid size-8 place-items-center rounded text-muted hover:text-fg" aria-label="{{ __('menu.move_down') }}" x-on:click="move($refs.p{{ $product->id }}, 1)"><x-ui.icon name="chevron-down" size="4" /></button>
                                            </span>
                                            <a href="{{ route('menu.products.edit', $product) }}" class="grid size-9 place-items-center rounded-lg text-muted hover:bg-surface-2 hover:text-fg" aria-label="{{ __('admin.edit') }}"><x-ui.icon name="pen" size="4" /></a>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </section>
        </div>
    @endif
</x-layouts.app>
