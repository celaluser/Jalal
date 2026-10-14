<x-layouts.admin :title="$post->exists ? $post->title : __('admin.cms.new_post')">
    <x-ui.page-header :title="$post->exists ? $post->title : __('admin.cms.new_post')" :back="['url' => route('admin.posts.index'), 'label' => __('admin.nav.blog')]" />
    <form method="POST" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}" enctype="multipart/form-data" class="max-w-3xl space-y-5">
        @csrf @if ($post->exists) @method('PUT') @endif
        <x-ui.card class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.select name="locale" :label="__('admin.cms.language')" :options="$locales" :value="$post->locale" />
                <x-ui.input name="title" :label="__('admin.cms.title')" :value="old('title', $post->title)" required />
                <x-ui.input name="slug" :label="__('admin.cms.slug')" :value="old('slug', $post->slug)" required />
            </div>
            <x-ui.input name="excerpt" :label="__('admin.cms.excerpt')" :value="old('excerpt', $post->excerpt)" />
            <div>
                <label for="body" class="mb-1.5 block text-sm font-medium">{{ __('admin.cms.body') }}</label>
                <textarea id="body" name="body" rows="18" required class="field font-mono">{{ old('body', $post->body) }}</textarea>
                @error('body')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                <p class="mt-1.5 text-xs text-muted">{{ __('admin.cms.markdown_help') }}</p>
            </div>
            <div>
                <label for="cover" class="mb-1.5 block text-sm font-medium">{{ __('admin.cms.cover') }}</label>
                @if ($post->cover_url)<div class="mb-3 flex items-center gap-4"><img src="{{ $post->cover_url }}" alt="" class="h-20 rounded-lg object-cover"><x-ui.checkbox name="remove_cover" :label="__('admin.settings.remove_image')" /></div>@endif
                <input id="cover" name="cover" type="file" accept="image/png,image/jpeg,image/gif,image/webp" class="block w-full text-sm file:me-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-line">
                @error('cover')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
            </div>
            <x-ui.input name="meta_description" :label="__('admin.cms.meta_description')" :value="old('meta_description', $post->meta_description)" />
            <div class="flex flex-wrap items-end gap-6">
                <x-ui.input name="published_at" type="datetime-local" :label="__('admin.cms.publish_date')" :value="old('published_at', $post->published_at?->format('Y-m-d\TH:i'))" />
                <div class="pb-2.5"><x-ui.checkbox name="is_published" :label="__('admin.cms.published')" :checked="$post->is_published" /></div>
            </div>
        </x-ui.card>
        <div class="flex gap-3"><x-ui.button :block="false" size="lg">{{ __('admin.save') }}</x-ui.button><a href="{{ route('admin.posts.index') }}" class="btn btn-ghost btn-lg">{{ __('admin.cancel') }}</a></div>
    </form>
</x-layouts.admin>
