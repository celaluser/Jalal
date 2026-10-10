<x-layouts.admin :title="__('admin.nav.currencies')">
    <x-ui.page-header :title="__('admin.nav.currencies')" :description="__('admin.localization.currencies_sub')" />
    <x-ui.table class="mb-6">
        <thead><tr><th>{{ __('admin.localization.code') }}</th><th>{{ __('admin.localization.name') }}</th><th>{{ __('admin.localization.symbol') }}</th><th>{{ __('admin.localization.position') }}</th><th>{{ __('admin.localization.decimals') }}</th><th>{{ __('admin.localization.separators') }}</th><th>{{ __('admin.localization.rate') }}</th><th>{{ __('admin.localization.preview') }}</th><th></th></tr></thead>
        <tbody>
            @foreach ($currencies as $c)
                <tr>
                    <form method="POST" action="{{ route('admin.currencies.update', $c) }}" id="cur-{{ $c->id }}">@csrf @method('PUT')</form>
                    <td class="font-mono font-semibold">{{ $c->code }}</td>
                    <td><input form="cur-{{ $c->id }}" name="name" value="{{ $c->name }}" class="field !py-1.5"></td>
                    <td><input form="cur-{{ $c->id }}" name="symbol" value="{{ $c->symbol }}" class="field !w-20 !py-1.5"></td>
                    <td><select form="cur-{{ $c->id }}" name="symbol_position" class="field !py-1.5"><option value="before" @selected($c->symbol_position === 'before')>{{ __('admin.localization.before') }}</option><option value="after" @selected($c->symbol_position === 'after')>{{ __('admin.localization.after') }}</option></select></td>
                    <td><input form="cur-{{ $c->id }}" type="number" min="0" max="4" name="decimals" value="{{ $c->decimals }}" class="field !w-16 !py-1.5"></td>
                    <td><div class="flex gap-1"><input form="cur-{{ $c->id }}" name="decimal_separator" value="{{ $c->decimal_separator }}" maxlength="1" class="field !w-12 !py-1.5 text-center" aria-label="{{ __('admin.localization.decimal_sep') }}"><input form="cur-{{ $c->id }}" name="thousands_separator" value="{{ $c->thousands_separator }}" maxlength="1" class="field !w-12 !py-1.5 text-center" aria-label="{{ __('admin.localization.thousands_sep') }}"></div></td>
                    <td><input form="cur-{{ $c->id }}" type="number" step="any" min="0" name="rate" value="{{ $c->rate !== null ? rtrim(rtrim((string) $c->rate, '0'), '.') : '' }}" class="field !w-28 !py-1.5" aria-label="{{ __('admin.localization.rate') }}"></td>
                    <td class="tnum text-muted"><bdi>{{ $c->format(1234567.891) }}</bdi></td>
                    <td class="whitespace-nowrap text-end"><label class="me-2 text-xs"><input form="cur-{{ $c->id }}" type="checkbox" name="is_active" value="1" @checked($c->is_active) @disabled($c->is_default) class="me-1 size-4 accent-[var(--color-accent-600)]">{{ __('admin.active') }}</label><button form="cur-{{ $c->id }}" class="btn btn-secondary btn-sm">{{ __('admin.save') }}</button></td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
    <x-ui.card :title="__('admin.localization.add_currency')">
        <form method="POST" action="{{ route('admin.currencies.store') }}" class="grid gap-4 sm:grid-cols-4">
            @csrf
            <x-ui.input name="code" :label="__('admin.localization.code')" placeholder="CHF" maxlength="3" required />
            <x-ui.input name="name" :label="__('admin.localization.name')" placeholder="Swiss franc" required />
            <x-ui.input name="symbol" :label="__('admin.localization.symbol')" placeholder="CHF" required />
            <x-ui.select name="symbol_position" :label="__('admin.localization.position')" :options="['before' => __('admin.localization.before'), 'after' => __('admin.localization.after')]" value="before" />
            <x-ui.input name="decimals" type="number" min="0" max="4" :label="__('admin.localization.decimals')" value="2" required />
            <x-ui.input name="decimal_separator" :label="__('admin.localization.decimal_sep')" value="." maxlength="1" required />
            <x-ui.input name="thousands_separator" :label="__('admin.localization.thousands_sep')" value="," maxlength="1" />
            <x-ui.input name="rate" type="number" step="any" min="0" :label="__('admin.localization.rate')" :hint="__('admin.localization.rate_hint')" />
            <div class="flex items-end"><x-ui.button :block="false">{{ __('admin.localization.add') }}</x-ui.button></div>
        </form>
    </x-ui.card>
</x-layouts.admin>
