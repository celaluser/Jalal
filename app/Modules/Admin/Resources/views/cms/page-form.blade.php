<x-layouts.admin :title="$page->exists ? $page->title : __('admin.cms.new_page')">
    <x-ui.page-header :title="$page->exists ? $page->title : __('admin.cms.new_page')" :back="['url' => route('admin.pages.index'), 'label' => __('admin.nav.pages')]" />
    <form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="max-w-3xl space-y-5">
        @csrf @if ($page->exists) @method('PUT') @endif
        <x-ui.card class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.select name="locale" :label="__('admin.cms.language')" :options="$locales" :value="$page->locale" />
                <x-ui.input name="title" :label="__('admin.cms.title')" :value="old('title', $page->title)" required />
                <x-ui.input name="slug" :label="__('admin.cms.slug')" :value="old('slug', $page->slug)" required placeholder="privacy-policy" />
            </div>
            <div>
                <label for="body" class="mb-1.5 block text-sm font-medium">{{ __('admin.cms.body') }}</label>
                <textarea id="body" name="body" rows="16" required class="field font-mono">{{ old('body', $page->body) }}</textarea>
                @error('body')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                <p class="mt-1.5 text-xs text-muted">{{ __('admin.cms.markdown_help') }}</p>
            </div>
            <x-ui.input name="meta_description" :label="__('admin.cms.meta_description')" :value="old('meta_description', $page->meta_description)" />
            <div class="flex flex-wrap items-end gap-6">
                <div class="w-28"><x-ui.input name="sort" type="number" min="0" :label="__('admin.plans.sort')" :value="old('sort', $page->sort)" /></div>
                <div class="pb-2.5"><x-ui.checkbox name="is_published" :label="__('admin.cms.published')" :checked="$page->is_published" /></div>
                <div class="pb-2.5"><x-ui.checkbox name="in_footer" :label="__('admin.cms.show_in_footer')" :checked="$page->in_footer" /></div>
            </div>
        </x-ui.card>
        <div class="flex gap-3"><x-ui.button :block="false" size="lg">{{ __('admin.save') }}</x-ui.button><a href="{{ route('admin.pages.index') }}" class="btn btn-ghost btn-lg">{{ __('admin.cancel') }}</a></div>
    </form>
</x-layouts.admin>
