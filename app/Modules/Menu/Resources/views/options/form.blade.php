@php
    $rows = old('options', $group->exists ? $group->options->map(fn ($o) => ['id' => $o->id, 'name' => $o->name, 'price_delta' => $o->price_delta, 'is_available' => $o->is_available, 'is_default' => $o->is_default])->all() : [['name' => [], 'price_delta' => '0.00', 'is_available' => true, 'is_default' => false]]);
    $blank = ['name' => (object) [], 'price_delta' => '0.00', 'is_available' => true, 'is_default' => false];
@endphp
<x-layouts.app :title="$group->exists ? __('menu.edit_group') : __('menu.new_group')">
    <x-ui.page-header :title="$group->exists ? __('menu.edit_group') : __('menu.new_group')" :back="['url' => route('menu.option-groups.index'), 'label' => __('menu.groups_title')]" />

    <form method="POST" action="{{ $group->exists ? route('menu.option-groups.update', $group) : route('menu.option-groups.store') }}" class="max-w-3xl space-y-5"
          x-data="{ type: @js(old('type', $group->type)), rows: @js(array_values($rows)), next: {{ count($rows) + 1 }}, blank: @js($blank),
                    add() { this.rows.push(JSON.parse(JSON.stringify(this.blank))); this.next++; },
                    remove(i) { this.rows.splice(i, 1); } }">
        @csrf @if ($group->exists) @method('PUT') @endif

        <x-ui.card>
            <div class="space-y-5">
                <x-ui.translatable name="name" :label="__('menu.group_name')" :locales="$locales" :values="$group->name ?? []" required :maxlength="120" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="type" class="mb-1.5 block text-sm font-medium">{{ __('menu.group_type') }}</label>
                        <select id="type" name="type" class="field" x-model="type">
                            @foreach (\App\Modules\Menu\Models\OptionGroup::TYPES as $t)<option value="{{ $t }}">{{ __('menu.type_'.$t) }}</option>@endforeach
                        </select>
                    </div>
                    <div x-show="type === 'multiple'" x-cloak>
                        <x-ui.input name="max_select" type="number" min="1" :label="__('menu.max_select')" :value="$group->max_select" :hint="__('menu.max_select_hint')" />
                    </div>
                </div>
                <x-ui.checkbox name="is_required" :label="__('menu.is_required')" :checked="$group->is_required" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('menu.options')">
            @error('options')<p class="mb-3 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
            <div class="space-y-3">
                <template x-for="(row, i) in rows" :key="i">
                    <div class="rounded-xl border border-line p-3">
                        <input type="hidden" :name="`options[${i}][id]`" :value="row.id ?? ''">
                        <div class="grid gap-3 sm:grid-cols-[1fr_9rem]">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-muted">{{ __('menu.option_name') }}</label>
                                <div class="space-y-2">
                                    @foreach ($locales as $code)
                                        <div class="flex items-center gap-2">
                                            @if (count($locales) > 1)<span class="w-7 shrink-0 text-xs font-semibold uppercase text-muted">{{ $code }}</span>@endif
                                            <input type="text" maxlength="120" dir="auto" class="field" :name="`options[${i}][name][{{ $code }}]`" x-model="row.name['{{ $code }}']" @if ($loop->first) required @endif>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-muted">{{ __('menu.price_delta') }}@if ($restaurant->currency_code) ({{ $restaurant->currency_code }})@endif</label>
                                <input type="number" step="0.01" inputmode="decimal" class="field" :name="`options[${i}][price_delta]`" x-model="row.price_delta">
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                                <label class="flex items-center gap-2"><input type="checkbox" class="check" value="1" :name="`options[${i}][is_available]`" :checked="!!row.is_available" x-on:change="row.is_available = $event.target.checked">{{ __('menu.option_available') }}</label>
                                <label class="flex items-center gap-2"><input type="checkbox" class="check" value="1" :name="`options[${i}][is_default]`" :checked="!!row.is_default" x-on:change="row.is_default = $event.target.checked">{{ __('menu.option_default') }}</label>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm text-red-600 dark:text-red-400" x-on:click="remove(i)" :disabled="rows.length < 2"><x-ui.icon name="trash" size="4" />{{ __('menu.remove_option') }}</button>
                        </div>
                    </div>
                </template>
            </div>
            <button type="button" class="btn btn-secondary btn-sm mt-4" x-on:click="add()"><x-ui.icon name="plus" size="4" />{{ __('menu.add_option') }}</button>
        </x-ui.card>

        <div class="flex items-center justify-between gap-3">
            <span>@if ($group->exists)<button type="submit" form="delete-group" class="btn btn-ghost text-red-600 dark:text-red-400">{{ __('admin.delete') }}</button>@endif</span>
            <x-ui.button :block="false" size="lg">{{ __('admin.save') }}</x-ui.button>
        </div>
    </form>
    @if ($group->exists)<form id="delete-group" method="POST" action="{{ route('menu.option-groups.destroy', $group) }}" onsubmit="return confirm('{{ __('menu.delete_confirm') }}')">@csrf @method('DELETE')</form>@endif
</x-layouts.app>
