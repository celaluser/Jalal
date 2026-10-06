<x-layouts.admin :title="$post->exists ? $post->title : __('admin.cms.new_post')">
    <form method="POST" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}" enctype="multipart/form-data" class="mx-auto max-w-3xl">
        @csrf @if ($post->exists) @method('PUT') @endif
        <x-ui.card class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.select name="locale" :label="__('admin.cms.language')" :options="$locales" :value="$post->locale" />
                <x-ui.input name="title" :label="__('admin.cms.title')" :value="old('title', $post->title)" required />
                <x-ui.input name="slug" :label="__('admin.cms.slug')" :value="old('slug', $post->slug)" required />
            </div>
            <x-ui.input name="excerpt" :label="__('admin.cms.excerpt')" :value="old('excerpt', $post->excerpt)" />
            <div>
                <label for="body" class="mb-1 block text-sm font-medium">{{ __('admin.cms.body') }}</label>
                <textarea id="body" name="body" rows="18" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-gray-700 dark:bg-gray-950">{{ old('body', $post->body) }}</textarea>
                @error('body')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-gray-500">{{ __('admin.cms.markdown_help') }}</p>
            </div>
            <div>
                <label for="cover" class="mb-1 block text-sm font-medium">{{ __('admin.cms.cover') }}</label>
                @if ($post->cover_url)<img src="{{ $post->cover_url }}" alt="" class="mb-2 h-24 rounded"><x-ui.checkbox name="remove_cover" :label="__('admin.settings.remove_image')" />@endif
                <input id="cover" name="cover" type="file" accept="image/png,image/jpeg,image/gif,image/webp" class="block w-full text-sm">
                @error('cover')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
            </div>
            <x-ui.input name="meta_description" :label="__('admin.cms.meta_description')" :value="old('meta_description', $post->meta_description)" />
            <div class="flex flex-wrap items-end gap-6">
                <x-ui.input name="published_at" type="datetime-local" :label="__('admin.cms.publish_date')" :value="old('published_at', $post->published_at?->format('Y-m-d\TH:i'))" />
                <x-ui.checkbox name="is_published" :label="__('admin.cms.published')" :checked="$post->is_published" />
            </div>
            <x-ui.button class="!w-auto">{{ __('admin.save') }}</x-ui.button>
        </x-ui.card>
    </form>
</x-layouts.admin>
