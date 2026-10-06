<x-layouts.site :title="$post->title" :description="$post->meta_description ?: $post->excerpt">
    <article class="mx-auto max-w-3xl px-4 py-12">
        <a href="{{ route('blog.index') }}" class="text-sm text-brand-600 hover:underline">← {{ __('site.nav.blog') }}</a>
        <h1 class="mt-3 text-4xl font-extrabold">{{ $post->title }}</h1>
        <p class="mt-2 text-sm text-gray-500">{{ ($post->published_at ?? $post->created_at)->toFormattedDateString() }}</p>
        @if ($post->cover_url)<img src="{{ $post->cover_url }}" alt="" class="mt-6 w-full rounded-2xl">@endif
        <div class="prose-content mt-8 space-y-4 leading-relaxed [&_a]:text-brand-600 [&_a]:underline [&_h2]:mt-8 [&_h2]:text-2xl [&_h2]:font-bold [&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-semibold [&_li]:ms-5 [&_ol]:list-decimal [&_ul]:list-disc [&_blockquote]:border-s-4 [&_blockquote]:ps-4 [&_img]:rounded-xl">{!! markdown_safe($post->body) !!}</div>
    </article>
</x-layouts.site>
