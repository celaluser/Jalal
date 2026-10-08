<x-layouts.admin :title="__('admin.nav.languages')">
    <x-ui.page-header :title="__('admin.nav.languages')" :description="__('admin.localization.languages_sub')" />
    <x-ui.table class="mb-6">
        <thead><tr><th>{{ __('admin.localization.code') }}</th><th>{{ __('admin.localization.name') }}</th><th>{{ __('admin.localization.rtl') }}</th><th>{{ __('admin.status') }}</th><th></th></tr></thead>
        <tbody>
            @foreach ($languages as $l)
                <tr>
                    <form method="POST" action="{{ route('admin.languages.update', $l) }}" id="lang-{{ $l->id }}">@csrf @method('PUT')</form>
                    <td class="font-mono font-semibold">{{ $l->code }}@if ($l->is_default) <x-ui.badge tone="success" class="ms-2">{{ __('admin.localization.default') }}</x-ui.badge>@endif</td>
                    <td><div class="flex gap-2"><input form="lang-{{ $l->id }}" name="name" value="{{ $l->name }}" class="field !py-1.5" aria-label="{{ __('admin.localization.name') }}"><input form="lang-{{ $l->id }}" name="native_name" value="{{ $l->native_name }}" class="field !py-1.5" aria-label="{{ __('admin.localization.native') }}"></div></td>
                    <td><input form="lang-{{ $l->id }}" type="checkbox" name="is_rtl" value="1" @checked($l->is_rtl) class="size-4 accent-[var(--color-accent-600)]" aria-label="{{ __('admin.localization.rtl') }}"></td>
                    <td><input form="lang-{{ $l->id }}" type="checkbox" name="is_active" value="1" @checked($l->is_active) @disabled($l->is_default) class="size-4 accent-[var(--color-accent-600)]" aria-label="{{ __('admin.active') }}"></td>
                    <td class="whitespace-nowrap text-end">
                        <button form="lang-{{ $l->id }}" class="btn btn-secondary btn-sm">{{ __('admin.save') }}</button>
                        @unless ($l->is_default)<form method="POST" action="{{ route('admin.languages.default', $l) }}" class="inline">@csrf<button class="btn btn-ghost btn-sm">{{ __('admin.localization.make_default') }}</button></form>@endunless
                        <a href="{{ route('admin.translations.index', ['locale' => $l->code]) }}" class="btn btn-ghost btn-sm">{{ __('admin.localization.edit_texts') }}</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
    <x-ui.card :title="__('admin.localization.add_language')" :description="__('admin.localization.add_language_help')">
        <form method="POST" action="{{ route('admin.languages.store') }}" class="grid gap-4 sm:grid-cols-4">
            @csrf
            <x-ui.input name="code" :label="__('admin.localization.code')" placeholder="de" required />
            <x-ui.input name="name" :label="__('admin.localization.name')" placeholder="German" required />
            <x-ui.input name="native_name" :label="__('admin.localization.native')" placeholder="Deutsch" />
            <div class="flex items-end gap-3"><x-ui.checkbox name="is_rtl" :label="__('admin.localization.rtl')" /><x-ui.button :block="false">{{ __('admin.localization.add') }}</x-ui.button></div>
        </form>
    </x-ui.card>
</x-layouts.admin>
