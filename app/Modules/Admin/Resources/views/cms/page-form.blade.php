<x-layouts.admin :title="$page->exists ? $page->title : __('admin.cms.new_page')">
    <form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="mx-auto max-w-3xl">
        @csrf @if ($page->exists) @method('PUT') @endif
        <x-ui.card class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.select name="locale" :label="__('admin.cms.language')" :options="$locales" :value="$page->locale" />
                <x-ui.input name="title" :label="__('admin.cms.title')" :value="old('title', $page->title)" required />
                <x-ui.input name="slug" :label="__('admin.cms.slug')" :value="old('slug', $page->slug)" required placeholder="privacy-policy" />
            </div>
            <div>
                <label for="body" class="mb-1 block text-sm font-medium">{{ __('admin.cms.body') }}</label>
                <textarea id="body" name="body" rows="16" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-gray-700 dark:bg-gray-950">{{ old('body', $page->body) }}</textarea>
                @error('body')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-gray-500">{{ __('admin.cms.markdown_help') }}</p>
            </div>
            <x-ui.input name="meta_description" :label="__('admin.cms.meta_description')" :value="old('meta_description', $page->meta_description)" />
            <div class="flex flex-wrap items-end gap-6">
                <div class="w-28"><x-ui.input name="sort" type="number" min="0" :label="__('admin.plans.sort')" :value="old('sort', $page->sort)" /></div>
                <x-ui.checkbox name="is_published" :label="__('admin.cms.published')" :checked="$page->is_published" />
                <x-ui.checkbox name="in_footer" :label="__('admin.cms.show_in_footer')" :checked="$page->in_footer" />
            </div>
            <x-ui.button class="!w-auto">{{ __('admin.save') }}</x-ui.button>
        </x-ui.card>
    </form>
</x-layouts.admin>
