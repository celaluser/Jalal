<x-layouts.site :title="$post->title" :description="$post->meta_description ?: $post->excerpt">
    <article class="mx-auto max-w-3xl px-4 py-14 sm:px-6 sm:py-20">
        <a href="{{ route('blog.index') }}" class="inline-flex items-center gap-1 text-sm text-muted hover:text-fg"><x-ui.icon name="chevron-right" size="4" class="rotate-180 rtl:rotate-0" />{{ __('site.nav.blog') }}</a>
        <h1 class="display mt-4 text-4xl font-semibold leading-[1.1] sm:text-5xl">{{ $post->title }}</h1>
        <p class="mt-4 text-sm text-muted">{{ ($post->published_at ?? $post->created_at)->toFormattedDateString() }}</p>
        @if ($post->cover_url)<img src="{{ $post->cover_url }}" alt="" class="mt-8 w-full rounded-3xl">@endif
        <div class="article mt-10">{!! markdown_safe($post->body) !!}</div>
    </article>
</x-layouts.site>
