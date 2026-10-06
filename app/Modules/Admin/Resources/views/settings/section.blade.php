<x-layouts.admin :title="__($schema['title'])">
    <div class="mx-auto max-w-3xl">
        <nav class="mb-4 flex flex-wrap gap-2 text-sm" aria-label="{{ __('admin.settings.sections') }}">
            @foreach ($sections as $key => $s)
                <a href="{{ route('admin.settings.section', $key) }}" @class(['rounded-full px-3 py-1', 'bg-brand-600 text-white' => $key === $section, 'bg-gray-200 hover:bg-gray-300 dark:bg-gray-800 dark:hover:bg-gray-700' => $key !== $section])
                   @if ($key === $section) aria-current="page" @endif>{{ __($s['title']) }}</a>
            @endforeach
        </nav>

        @error('mail')<x-ui.alert type="error">{{ $message }}</x-ui.alert>@enderror
        @if (! empty($schema['description']))<p class="mb-4 text-sm text-gray-500">{{ __($schema['description']) }}</p>@endif

        <form method="POST" action="{{ route('admin.settings.section.update', $section) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <x-ui.card class="space-y-4">
                @foreach ($schema['fields'] as $field)
                    @php($name = $field['name'])
                    @php($label = __($field['label']))
                    @switch($field['type'])
                        @case('toggle')
                            <div>
                                <x-ui.checkbox :name="$name" :label="$label" :checked="($values[$name] ?? '0') === '1'" />
                                @if (! empty($field['help']))<p class="ms-6 mt-0.5 text-xs text-gray-500">{{ __($field['help']) }}</p>@endif
                            </div>
                            @break
                        @case('select')
                            <x-ui.select :name="$name" :label="$label" :options="$options($field)" :value="$values[$name]" />
                            @break
                        @case('textarea')
                            <div>
                                <label for="{{ $name }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
                                <textarea id="{{ $name }}" name="{{ $name }}" rows="4" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950">{{ old($name, $values[$name]) }}</textarea>
                                @error($name)<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                                @if (! empty($field['help']))<p class="mt-1 text-xs text-gray-500">{{ __($field['help']) }}</p>@endif
                            </div>
                            @break
                        @case('secret')
                            {{-- Stored secrets are never sent back to the browser; blank keeps the saved value. --}}
                            <x-ui.input :name="$name" type="password" :label="$label" autocomplete="off"
                                        :placeholder="$configured[$name] ? '•••••••• '.__('admin.payments.saved') : ''" />
                            @break
                        @case('image')
                            <div>
                                <label for="{{ $name }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
                                @if ($values[$name])
                                    <img src="{{ $values[$name] }}" alt="" class="mb-2 h-14 rounded border border-gray-200 bg-white p-1 dark:border-gray-700">
                                    <x-ui.checkbox :name="'remove_'.$name" :label="__('admin.settings.remove_image')" />
                                @endif
                                <input id="{{ $name }}" name="{{ $name }}" type="file" accept="image/png,image/jpeg,image/gif,image/webp" class="mt-1 block w-full text-sm">
                                @error($name)<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                            </div>
                            @break
                        @default
                            <div>
                                <x-ui.input :name="$name" :type="$field['type'] === 'number' ? 'number' : ($field['type'] === 'email' ? 'email' : 'text')" :label="$label" :value="$values[$name]" autocomplete="off" />
                                @if (! empty($field['help']))<p class="mt-1 text-xs text-gray-500">{{ __($field['help']) }}</p>@endif
                            </div>
                    @endswitch
                @endforeach
                <x-ui.button class="!w-auto">{{ __('admin.save') }}</x-ui.button>
            </x-ui.card>
        </form>

        @if ($section === 'mail')
            <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="mt-4">@csrf
                <x-ui.button variant="secondary" class="!w-auto">{{ __('admin.settings.mail.send_test') }}</x-ui.button>
            </form>
        @endif
    </div>
</x-layouts.admin>
