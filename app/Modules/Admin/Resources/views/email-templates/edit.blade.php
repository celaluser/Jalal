<x-layouts.admin :title="$definition['label']">
    <div class="mx-auto max-w-3xl space-y-4">
        <nav class="flex flex-wrap gap-2 text-sm" aria-label="{{ __('admin.email_templates.languages') }}">
            @foreach ($locales as $code => $name)
                <a href="{{ route('admin.email-templates.edit', [$key, 'locale' => $code]) }}" @class(['rounded-full px-3 py-1', 'bg-brand-600 text-white' => $code === $locale, 'bg-gray-200 dark:bg-gray-800' => $code !== $locale])>{{ $name }}</a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('admin.email-templates.update', $key) }}">
            @csrf @method('PUT')
            <input type="hidden" name="locale" value="{{ $locale }}">
            <x-ui.card class="space-y-4">
                <x-ui.input name="subject" :label="__('admin.email_templates.subject')" :value="$subject" required />
                <div>
                    <label for="body" class="mb-1 block text-sm font-medium">{{ __('admin.email_templates.body') }}</label>
                    <textarea id="body" name="body" rows="12" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-gray-700 dark:bg-gray-950">{{ $body }}</textarea>
                    @error('body')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.email_templates.markdown_help') }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-3 text-xs dark:bg-gray-800">
                    <p class="mb-1 font-semibold">{{ __('admin.email_templates.variables') }}</p>
                    <p class="font-mono">@foreach ($definition['variables'] as $variable)<span class="me-2">&#123;&#123;{{ $variable }}&#125;&#125;</span>@endforeach</p>
                </div>
                @unless ($definition['required'])
                    <x-ui.checkbox name="is_active" :label="__('admin.email_templates.send_this')" :checked="$active" />
                @endunless
                <div class="flex flex-wrap gap-2">
                    <x-ui.button class="!w-auto">{{ __('admin.save') }}</x-ui.button>
                    <x-ui.button variant="secondary" class="!w-auto" formaction="{{ route('admin.email-templates.preview', $key) }}" formtarget="_blank">{{ __('admin.email_templates.preview') }}</x-ui.button>
                </div>
            </x-ui.card>
        </form>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('admin.email-templates.test', $key) }}">@csrf<input type="hidden" name="locale" value="{{ $locale }}"><x-ui.button variant="secondary" class="!w-auto">{{ __('admin.email_templates.send_test') }}</x-ui.button></form>
            @if ($customised)
                <form method="POST" action="{{ route('admin.email-templates.reset', $key) }}" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf @method('DELETE')<input type="hidden" name="locale" value="{{ $locale }}"><x-ui.button variant="secondary" class="!w-auto">{{ __('admin.email_templates.reset') }}</x-ui.button></form>
            @endif
        </div>
    </div>
</x-layouts.admin>
