<x-layouts.admin :title="__($schema['title'])">
    <x-ui.page-header :title="__('admin.nav.settings')" :description="__($schema['description'] ?? 'admin.settings.default_description')" />
    <div class="grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)]">
        <nav class="flex gap-1 overflow-x-auto lg:flex-col lg:overflow-visible" aria-label="{{ __('admin.settings.sections') }}">
            @foreach ($sections as $key => $s)
                <a href="{{ route('admin.settings.section', $key) }}" @class(['whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition', 'bg-surface text-fg shadow-card ring-1 ring-line' => $key === $section, 'text-muted hover:bg-surface-2 hover:text-fg' => $key !== $section])
                   @if ($key === $section) aria-current="page" @endif>{{ __($s['title']) }}</a>
            @endforeach
        </nav>
        <div class="max-w-2xl space-y-5">
            @error('mail')<x-ui.alert type="error">{{ $message }}</x-ui.alert>@enderror
            <form method="POST" action="{{ route('admin.settings.section.update', $section) }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                <x-ui.card :title="__($schema['title'])" class="space-y-5">
                    <div class="space-y-5">
                    @foreach ($schema['fields'] as $field)
                        @php($name = $field['name'])
                        @php($label = __($field['label']))
                        @switch($field['type'])
                            @case('toggle')
                                <div class="rounded-xl border border-line bg-surface-2/40 p-3.5">
                                    <x-ui.checkbox :name="$name" :label="$label" :checked="($values[$name] ?? '0') === '1'" />
                                    @if (! empty($field['help']))<p class="ms-[1.625rem] mt-1 text-xs text-muted">{{ __($field['help']) }}</p>@endif
                                </div>
                                @break
                            @case('select')
                                <x-ui.select :name="$name" :label="$label" :options="$options($field)" :value="$values[$name]" />
                                @break
                            @case('textarea')
                                <div>
                                    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>
                                    <textarea id="{{ $name }}" name="{{ $name }}" rows="4" class="field">{{ old($name, $values[$name]) }}</textarea>
                                    @error($name)<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                                    @if (! empty($field['help']))<p class="mt-1.5 text-xs text-muted">{{ __($field['help']) }}</p>@endif
                                </div>
                                @break
                            @case('secret')
                                {{-- Stored secrets are never sent back to the browser; blank keeps the saved value. --}}
                                <x-ui.input :name="$name" type="password" :label="$label" autocomplete="off" :placeholder="$configured[$name] ? '•••••••• '.__('admin.payments.saved') : ''" />
                                @break
                            @case('image')
                                <div>
                                    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>
                                    @if ($values[$name])
                                        <div class="mb-3 flex items-center gap-4"><img src="{{ $values[$name] }}" alt="" class="h-14 rounded-lg border border-line bg-white p-1.5"><x-ui.checkbox :name="'remove_'.$name" :label="__('admin.settings.remove_image')" /></div>
                                    @endif
                                    <input id="{{ $name }}" name="{{ $name }}" type="file" accept="image/png,image/jpeg,image/gif,image/webp" class="block w-full text-sm file:me-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-line">
                                    @error($name)<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                                </div>
                                @break
                            @default
                                <x-ui.input :name="$name" :type="$field['type'] === 'number' ? 'number' : ($field['type'] === 'email' ? 'email' : 'text')" :label="$label" :value="$values[$name]" autocomplete="off" :hint="! empty($field['help']) ? __($field['help']) : null" />
                        @endswitch
                    @endforeach
                    </div>
                    <div class="flex flex-wrap gap-3 border-t border-line pt-5">
                        <x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button>
                    </div>
                </x-ui.card>
            </form>
            @if ($section === 'mail')
                {{-- Own form: the settings form above spoofs PUT, this route is POST --}}
                <form method="POST" action="{{ route('admin.settings.mail.test') }}">@csrf<x-ui.button variant="secondary" :block="false" icon="mail">{{ __('admin.settings.mail.send_test') }}</x-ui.button></form>
            @endif
        </div>
    </div>
</x-layouts.admin>
