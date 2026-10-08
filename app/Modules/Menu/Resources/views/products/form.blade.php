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

            <x-ui.card :title="__('menu.section_variants')" :description="__('menu.variants_help')">
                <div x-data="{ rows: @js(old('variants') !== null ? collect(old('variants'))->values()->all() : $product->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price' => (string) $v->price, 'is_available' => $v->is_available ? 1 : 0])->values()->all()), locales: @js($locales) }" class="space-y-3">
                    <template x-for="(row, i) in rows" :key="i">
                        <div class="grid items-end gap-3 rounded-xl border border-line p-3 sm:grid-cols-[1fr_8rem_auto_auto]">
                            <input type="hidden" :name="`variants[${i}][id]`" :value="row.id || ''">
                            <div class="space-y-1.5">
                                <template x-for="loc in locales" :key="loc">
                                    <div class="flex items-center gap-2"><span class="w-7 shrink-0 text-xs font-semibold uppercase text-muted" x-text="loc"></span>
                                        <input class="field !py-1.5" :name="`variants[${i}][name][${loc}]`" x-model="(row.name = row.name || {})[loc]" maxlength="80" :placeholder="@js(__('menu.variant_name'))" :aria-label="@js(__('menu.variant_name')) + ' ' + loc"></div>
                                </template>
                            </div>
                            <div><label class="mb-1 block text-xs text-muted">{{ __('menu.price') }} ({{ $restaurant->currency_code }})</label><input class="field tnum !py-1.5" type="number" step="0.01" min="0" :name="`variants[${i}][price]`" x-model="row.price"></div>
                            <label class="flex items-center gap-1.5 pb-2 text-sm"><input type="checkbox" class="check" :name="`variants[${i}][is_available]`" value="1" :checked="row.is_available != 0" x-on:change="row.is_available = $event.target.checked ? 1 : 0"> {{ __('menu.variant_available') }}</label>
                            <button type="button" class="btn btn-ghost btn-sm mb-1 text-red-600" x-on:click="rows.splice(i, 1)" aria-label="{{ __('admin.delete') }}"><x-ui.icon name="trash" size="4" /></button>
                        </div>
                    </template>
                    <button type="button" class="btn btn-secondary btn-sm" x-show="rows.length < 12" x-on:click="rows.push({ id: null, name: {}, price: '', is_available: 1 })"><x-ui.icon name="plus" size="4" />{{ __('menu.add_variant') }}</button>
                    <p class="text-xs text-muted" x-show="rows.length">{{ __('menu.variants_price_note') }}</p>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('menu.section_media')" :description="__('menu.media_help')">
                <div class="space-y-5">
                    <div>
                        <p class="mb-2 text-sm font-medium">{{ __('menu.gallery') }}</p>
                        @if ($product->gallery)
                            <div class="mb-3 flex flex-wrap gap-3">
                                @foreach (\App\Modules\Core\Models\Media::whereIn('id', $product->gallery)->get() as $m)
                                    <label class="relative block"><img src="{{ $m->thumbUrl() }}" alt="" class="size-20 rounded-xl object-cover ring-1 ring-line">
                                        <span class="absolute inset-x-1 bottom-1 flex items-center gap-1 rounded-md bg-ink-950/75 px-1.5 py-0.5 text-[11px] text-white"><input type="checkbox" name="remove_gallery[]" value="{{ $m->id }}" class="size-3"> {{ __('admin.delete') }}</span></label>
                                @endforeach
                            </div>
                        @endif
                        <input type="file" name="gallery[]" accept="image/*" multiple class="field !py-2" aria-label="{{ __('menu.gallery') }}">
                        <p class="mt-1.5 text-xs text-muted">{{ __('menu.gallery_hint', ['count' => \App\Modules\Menu\Services\ProductMedia::MAX_GALLERY]) }}</p>
                        @error('gallery.*')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <x-ui.input name="video_url" type="url" :label="__('menu.video_url')" :value="$product->video_url" :hint="__('menu.video_hint')" placeholder="https://www.youtube.com/watch?v=…" dir="ltr" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('menu.section_nutrition')" :description="__('menu.nutrition_help')">
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach (\App\Modules\Menu\Models\Product::NUTRIENTS as $n)
                        <x-ui.input :name="'nutrition['.$n.']'" type="number" step="0.1" min="0" inputmode="decimal" :label="__('menu.nutrient_'.$n)" :value="$product->nutrition[$n] ?? null" />
                    @endforeach
                    <x-ui.input name="portion_size" :label="__('menu.portion_size')" :value="$product->portion_size" :hint="__('menu.portion_hint')" />
                    @php($stations = app(\App\Modules\Orders\Services\OrderSettings::class)->stations(auth()->user()->restaurant))
                    @if ($stations)<x-ui.select name="station" :label="__('orders.station')" :options="array_combine($stations, $stations)" :value="old('station', $product->station)" placeholder="—" />@endif
                    <x-ui.select name="spice_level" :label="__('menu.spice_level')" :options="[0 => __('menu.spice_0'), 1 => __('menu.spice_1'), 2 => __('menu.spice_2'), 3 => __('menu.spice_3')]" :value="old('spice_level', $product->spice_level ?? 0)" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('menu.section_combo')" :description="__('menu.combo_help')">
                <div x-data="{ on: {{ old('is_combo', $product->is_combo) ? 'true' : 'false' }}, slots: @js(old('combo.slots') !== null ? collect(old('combo.slots'))->values()->all() : $comboSlots), locales: @js($locales), dishes: @js($otherProducts->map(fn ($o) => ['id' => $o->id, 'name' => $o->tr('name', null, $restaurant->locale)])->values()->all()) }" class="space-y-4">
                    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="is_combo" value="1" class="check" x-model="on"> {{ __('menu.is_combo') }}</label>
                    <div x-show="on" x-cloak class="space-y-3">
                        <template x-for="(slot, i) in slots" :key="i">
                            <div class="space-y-3 rounded-xl border border-line p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1 space-y-1.5">
                                        <template x-for="loc in locales" :key="loc">
                                            <div class="flex items-center gap-2"><span class="w-7 shrink-0 text-xs font-semibold uppercase text-muted" x-text="loc"></span>
                                                <input class="field !py-1.5" :name="`combo[slots][${i}][name][${loc}]`" x-model="(slot.name = slot.name || {})[loc]" maxlength="80" :placeholder="@js(__('menu.combo_slot_name'))"></div>
                                        </template>
                                    </div>
                                    <button type="button" class="btn btn-ghost btn-sm text-red-600" x-on:click="slots.splice(i, 1)" aria-label="{{ __('admin.delete') }}"><x-ui.icon name="trash" size="4" /></button>
                                </div>
                                <div class="space-y-2">
                                    <template x-for="(item, j) in (slot.items = slot.items || [])" :key="j">
                                        <div class="flex items-center gap-2">
                                            <select class="field !py-1.5" :name="`combo[slots][${i}][items][${j}][product_id]`" x-model="item.product_id"><option value="">{{ __('menu.combo_pick_dish') }}</option><template x-for="d in dishes" :key="d.id"><option :value="d.id" x-text="d.name" :selected="String(d.id) === String(item.product_id)"></option></template></select>
                                            <input class="field tnum !w-24 !py-1.5" type="number" step="0.01" min="0" :name="`combo[slots][${i}][items][${j}][price_delta]`" x-model="item.price_delta" placeholder="+0.00" :aria-label="@js(__('menu.combo_extra'))">
                                            <button type="button" class="btn btn-ghost btn-sm" x-on:click="slot.items.splice(j, 1)" aria-label="{{ __('admin.delete') }}"><x-ui.icon name="x" size="4" /></button>
                                        </div>
                                    </template>
                                    <button type="button" class="btn btn-secondary btn-sm" x-on:click="slot.items.push({ product_id: '', price_delta: '0' })"><x-ui.icon name="plus" size="4" />{{ __('menu.combo_add_dish') }}</button>
                                </div>
                            </div>
                        </template>
                        <button type="button" class="btn btn-secondary btn-sm" x-show="slots.length < 8" x-on:click="slots.push({ name: {}, items: [{ product_id: '', price_delta: '0' }] })"><x-ui.icon name="plus" size="4" />{{ __('menu.combo_add_slot') }}</button>
                        <p class="text-xs text-muted">{{ __('menu.combo_price_note') }}</p>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('menu.section_pairings')" :description="__('menu.pairings_help')">
                @if ($otherProducts->isEmpty())
                    <p class="text-sm text-muted">{{ __('menu.pairings_none') }}</p>
                @else
                    <div class="max-h-64 space-y-1 overflow-y-auto rounded-xl border border-line p-2">
                        @foreach ($otherProducts as $other)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-surface-2"><input type="checkbox" name="pairings[]" value="{{ $other->id }}" class="check" @checked(in_array($other->id, old('pairings', $pairedIds)))> {{ $other->tr('name', null, $restaurant->locale) }}</label>
                        @endforeach
                    </div>
                @endif
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
                    <x-ui.input name="cost_price" type="number" step="0.01" min="0" inputmode="decimal" :label="__('menu.cost_price')" :value="$product->cost_price" :hint="__('menu.cost_price_hint')" />
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
            <x-ui.card :title="__('menu.section_badges')">
                <div class="space-y-3">
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Modules\Menu\Models\Product::BADGES as $b)
                            <label class="cursor-pointer"><input type="checkbox" name="badges[]" value="{{ $b }}" class="peer sr-only" @checked(in_array($b, old('badges', $product->badges ?? [])))>
                                <span class="inline-flex rounded-full border border-line-strong px-3 py-1.5 text-sm transition peer-checked:border-accent-600 peer-checked:bg-accent-50 peer-checked:font-medium peer-focus-visible:ring-2 peer-focus-visible:ring-accent-500 dark:peer-checked:bg-accent-900/20">{{ __('menu.badge_'.$b) }}</span></label>
                        @endforeach
                    </div>
                    <x-ui.input name="limited_until" type="date" :label="__('menu.limited_until')" :value="$product->limited_until?->toDateString()" :hint="__('menu.limited_until_hint')" />
                </div>
            </x-ui.card>
            <x-ui.card :title="__('menu.section_availability')">
                <div class="space-y-5">
                    @include('menu::partials.schedule', ['schedule' => $product->schedule])
                    <fieldset>
                        <legend class="mb-1 text-sm font-medium">{{ __('menu.order_types') }}</legend>
                        <p class="mb-2 text-xs text-muted">{{ __('menu.order_types_help') }}</p>
                        <div class="space-y-2">
                            @foreach (['dine_in', 'takeaway', 'delivery'] as $t)
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="order_types[]" value="{{ $t }}" class="check" @checked(in_array($t, old('order_types', $product->order_types ?? ['dine_in', 'takeaway', 'delivery'])))> {{ __('orders.type_'.$t) }}</label>
                            @endforeach
                        </div>
                    </fieldset>
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
