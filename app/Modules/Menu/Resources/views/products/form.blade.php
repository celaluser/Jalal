<x-layouts.app :title="$product->exists ? __('menu.edit_product') : __('menu.new_product')">
    <x-ui.page-header :title="$product->exists ? __('menu.edit_product') : __('menu.new_product')" :back="['url' => route('menu.index', ['category' => $product->category_id]), 'label' => __('menu.title')]">
        @if ($product->exists)
            <x-slot:actions>
                <form method="POST" action="{{ route('menu.products.duplicate', $product) }}">@csrf<button class="btn btn-secondary btn-sm"><x-ui.icon name="plus" size="4" />{{ __('menu.duplicate') }}</button></form>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @error('limit')<x-ui.alert type="warning" class="mb-4"><span class="flex flex-wrap items-center justify-between gap-2"><span>{{ $message }}</span><a class="font-semibold underline" href="{{ route('billing.index') }}">{{ __('menu.upgrade') }}</a></span></x-ui.alert>@enderror

    <form method="POST" enctype="multipart/form-data" action="{{ $product->exists ? route('menu.products.update', $product) : route('menu.products.store') }}" class="grid gap-5 lg:grid-cols-3" x-data="aiTools(@js($ai))">
        @csrf @if ($product->exists) @method('PUT') @endif

        <div class="space-y-5 lg:col-span-2">
            <x-ui.card :title="__('menu.section_basics')">
                <div class="space-y-5">
                    <x-ui.translatable name="name" :label="__('menu.name')" :locales="$locales" :values="$product->name ?? []" required />
                    <div>
                        <x-ui.translatable name="description" :label="__('menu.description')" :locales="$locales" :values="$product->description ?? []" textarea :maxlength="1000" />
                        @include('ai::buttons', ['mode' => 'description'])
                        @include('ai::buttons', ['mode' => 'translate'])
                    </div>
                    @include('menu::partials.image', ['model' => $product])
                </div>
            </x-ui.card>

            <x-ui.card :title="__('menu.section_dietary')">
                <div class="space-y-5">
                    <fieldset>
                        <legend class="mb-1 text-sm font-medium">{{ __('menu.section_dietary') }}</legend>
                        <p class="mb-2 text-xs text-muted">{{ __('menu.dietary_help') }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($dietary as $key)
                                <label class="cursor-pointer"><input type="checkbox" name="dietary[]" value="{{ $key }}" class="peer sr-only" @checked(in_array($key, old('dietary', $product->dietary ?? [])))>
                                    <span class="inline-flex items-center rounded-full border border-line-strong px-3 py-1.5 text-sm transition peer-checked:border-accent-600 peer-checked:bg-accent-50 peer-checked:font-medium peer-checked:text-accent-800 peer-focus-visible:ring-2 peer-focus-visible:ring-accent-500 dark:peer-checked:bg-accent-900/30 dark:peer-checked:text-accent-200">{{ __('menu.diet.'.$key) }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend class="mb-1 text-sm font-medium">{{ __('menu.allergens') }}</legend>
                        <p class="mb-2 text-xs text-muted">{{ __('menu.allergens_help') }}</p>
                        @include('ai::buttons', ['mode' => 'tags'])
                        <div class="grid gap-x-4 gap-y-2 sm:grid-cols-2 md:grid-cols-3">
                            @foreach ($allergens as $key)
                                <label class="flex cursor-pointer items-center gap-2 text-sm"><input type="checkbox" name="allergens[]" value="{{ $key }}" class="check" @checked(in_array($key, old('allergens', $product->allergens ?? [])))>{{ __('menu.allergen.'.$key) }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('menu.section_options')" :description="__('menu.options_help')">
                @if ($groups->isEmpty())
                    <p class="text-sm text-muted">{{ __('menu.no_groups') }} <a class="link" href="{{ route('menu.option-groups.create') }}">{{ __('menu.create_group') }}</a></p>
                @else
                    <div class="grid gap-2">
                        @foreach ($groups as $group)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line-strong p-3 transition hover:bg-surface-2 has-[:checked]:border-accent-600 has-[:checked]:bg-accent-50 dark:has-[:checked]:bg-accent-900/20">
                                <input type="checkbox" name="option_groups[]" value="{{ $group->id }}" class="check mt-0.5" @checked(in_array($group->id, old('option_groups', $selectedGroups)))>
                                <span class="min-w-0"><span class="block font-medium">{{ $group->tr('name', null, $restaurant->locale) }}</span>
                                    <span class="block truncate text-xs text-muted">{{ $group->options->map(fn ($o) => $o->tr('name', null, $restaurant->locale))->implode(' · ') }}</span></span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-5">
            <x-ui.card :title="__('menu.section_pricing')">
                <div class="space-y-4">
                    <x-ui.select name="category_id" :label="__('menu.category')" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->tr('name', null, $restaurant->locale)])->all()" :value="$product->category_id" required />
                    <x-ui.input name="price" type="number" step="0.01" min="0" inputmode="decimal" :label="__('menu.price').($restaurant->currency_code ? ' ('.$restaurant->currency_code.')' : '')" :value="$product->price" required />
                    <x-ui.input name="compare_price" type="number" step="0.01" min="0" inputmode="decimal" :label="__('menu.compare_price')" :value="$product->compare_price" :hint="__('menu.compare_price_hint')" />
                </div>
            </x-ui.card>
            <x-ui.card :title="__('menu.section_details')">
                <div class="space-y-4">
                    <x-ui.input name="calories" type="number" min="0" inputmode="numeric" :label="__('menu.calories')" :value="$product->calories" />
                    <x-ui.input name="prep_minutes" type="number" min="0" inputmode="numeric" :label="__('menu.prep_minutes')" :value="$product->prep_minutes" />
                </div>
            </x-ui.card>
            <x-ui.card :title="__('menu.section_stock')" :description="__('menu.stock_help')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="stock_qty" type="number" min="0" inputmode="numeric" :label="__('menu.stock_qty')" :value="$product->stock_qty" :hint="__('menu.stock_qty_hint')" />
                    <x-ui.input name="low_stock_at" type="number" min="0" inputmode="numeric" :label="__('menu.low_stock_at')" :value="$product->low_stock_at" :hint="__('menu.low_stock_hint')" />
                </div>
            </x-ui.card>
            <x-ui.card :title="__('menu.section_visibility')">
                <div class="space-y-3">
                    <x-ui.checkbox name="is_active" :label="__('menu.is_active')" :checked="$product->is_active" />
                    <x-ui.checkbox name="is_available" :label="__('menu.is_available')" :checked="$product->is_available" />
                    <x-ui.checkbox name="is_featured" :label="__('menu.is_featured')" :checked="$product->is_featured" />
                </div>
            </x-ui.card>
            <div class="flex items-center justify-between gap-3">
                <span>@if ($product->exists)<button type="submit" form="delete-product" class="btn btn-ghost text-red-600 dark:text-red-400">{{ __('admin.delete') }}</button>@endif</span>
                <x-ui.button :block="false" size="lg">{{ __('admin.save') }}</x-ui.button>
            </div>
        </div>
    </form>
    @if ($product->exists)<form id="delete-product" method="POST" action="{{ route('menu.products.destroy', $product) }}" onsubmit="return confirm('{{ __('menu.delete_confirm') }}')">@csrf @method('DELETE')</form>@endif
</x-layouts.app>
