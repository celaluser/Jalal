<x-layouts.admin :title="$definition['label']">
    <x-ui.page-header :title="$definition['label']" :back="['url' => route('admin.email-templates.index'), 'label' => __('admin.nav.email_templates')]" />
    <div class="max-w-3xl space-y-5">
        <nav class="flex flex-wrap gap-1.5 text-sm" aria-label="{{ __('admin.email_templates.languages') }}">
            @foreach ($locales as $code => $name)
                <a href="{{ route('admin.email-templates.edit', [$key, 'locale' => $code]) }}" @class(['rounded-lg px-3 py-1.5 font-medium transition', 'bg-ink-950 text-white dark:bg-white dark:text-ink-950' => $code === $locale, 'bg-surface-2 text-muted hover:text-fg' => $code !== $locale])>{{ $name }}</a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('admin.email-templates.update', $key) }}">
            @csrf @method('PUT')
            <input type="hidden" name="locale" value="{{ $locale }}">
            <x-ui.card class="space-y-5">
                <x-ui.input name="subject" :label="__('admin.email_templates.subject')" :value="$subject" required />
                <div>
                    <label for="body" class="mb-1.5 block text-sm font-medium">{{ __('admin.email_templates.body') }}</label>
                    <textarea id="body" name="body" rows="12" required class="field font-mono">{{ $body }}</textarea>
                    @error('body')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                    <p class="mt-1.5 text-xs text-muted">{{ __('admin.email_templates.markdown_help') }}</p>
                </div>
                <div class="rounded-xl bg-surface-2 p-4">
                    <p class="eyebrow mb-2">{{ __('admin.email_templates.variables') }}</p>
                    <div class="flex flex-wrap gap-1.5">@foreach ($definition['variables'] as $variable)<code class="rounded-md bg-surface px-2 py-0.5 font-mono text-xs ring-1 ring-line">&#123;&#123;{{ $variable }}&#125;&#125;</code>@endforeach</div>
                </div>
                @unless ($definition['required'])<x-ui.checkbox name="is_active" :label="__('admin.email_templates.send_this')" :checked="$active" />@endunless
                <div class="flex flex-wrap gap-2 border-t border-line pt-5">
                    <x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button>
                    <x-ui.button variant="secondary" :block="false" icon="eye" formaction="{{ route('admin.email-templates.preview', $key) }}" formtarget="_blank">{{ __('admin.email_templates.preview') }}</x-ui.button>
                </div>
            </x-ui.card>
        </form>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('admin.email-templates.test', $key) }}">@csrf<input type="hidden" name="locale" value="{{ $locale }}"><x-ui.button variant="secondary" :block="false" size="sm" icon="mail">{{ __('admin.email_templates.send_test') }}</x-ui.button></form>
            @if ($customised)
                <form method="POST" action="{{ route('admin.email-templates.reset', $key) }}" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf @method('DELETE')<input type="hidden" name="locale" value="{{ $locale }}"><x-ui.button variant="ghost" :block="false" size="sm">{{ __('admin.email_templates.reset') }}</x-ui.button></form>
            @endif
        </div>
    </div>
</x-layouts.admin>
