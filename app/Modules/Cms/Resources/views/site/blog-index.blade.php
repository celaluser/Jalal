<x-layouts.site :title="__('site.nav.blog')">
    <div class="mx-auto max-w-5xl px-4 py-12">
        <h1 class="mb-8 text-3xl font-bold">{{ __('site.nav.blog') }}</h1>
        <div class="grid gap-6 md:grid-cols-3">
            @forelse ($posts as $post)
                <article class="overflow-hidden rounded-2xl bg-white ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                    @if ($post->cover_url)<img src="{{ $post->cover_url }}" alt="" loading="lazy" class="h-40 w-full object-cover">@endif
                    <div class="p-5">
                        <h2 class="font-semibold"><a class="hover:text-brand-600" href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a></h2>
                        <p class="mt-1 text-xs text-gray-500">{{ ($post->published_at ?? $post->created_at)->toFormattedDateString() }}</p>
                        @if ($post->excerpt)<p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $post->excerpt }}</p>@endif
                    </div>
                </article>
            @empty
                <p class="text-gray-500 md:col-span-3">{{ __('site.blog.empty') }}</p>
            @endforelse
        </div>
        <div class="mt-8">{{ $posts->links() }}</div>
    </div>
</x-layouts.site>
