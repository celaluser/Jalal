<x-layouts.site :title="__('site.nav.blog')">
    <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20">
        <div class="max-w-2xl"><p class="eyebrow text-accent-700 dark:text-accent-300">{{ __('site.nav.blog') }}</p><h1 class="display mt-3 text-4xl font-semibold sm:text-5xl">{{ __('site.blog.title') }}</h1></div>
        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($posts as $post)
                <a href="{{ route('blog.show', $post->slug) }}" class="card group flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-pop">
                    @if ($post->cover_url)<img src="{{ $post->cover_url }}" alt="" loading="lazy" class="aspect-[16/9] w-full object-cover">
                    @else<div class="relative grid aspect-[16/9] place-items-center overflow-hidden bg-ink-950"><x-ui.qr-pattern class="absolute inset-0 m-auto size-full text-white/[0.08]" :cells="21" :seed="crc32($post->slug) % 97" /><x-ui.qr-mark size="12" class="relative" /></div>@endif
                    <div class="flex flex-1 flex-col p-5">
                        <p class="text-xs font-medium text-muted">{{ ($post->published_at ?? $post->created_at)->toFormattedDateString() }}</p>
                        <h2 class="display mt-2 text-xl font-semibold leading-snug group-hover:text-link">{{ $post->title }}</h2>
                        @if ($post->excerpt)<p class="mt-2 flex-1 text-sm text-muted">{{ $post->excerpt }}</p>@endif
                        <span class="link mt-4 inline-flex items-center gap-1 text-sm">{{ __('site.blog.read') }}<x-ui.icon name="arrow-right" size="4" class="rtl:rotate-180" /></span>
                    </div>
                </a>
            @empty
                <div class="card sm:col-span-2 lg:col-span-3"><x-ui.empty icon="pen" :title="__('site.blog.empty')" /></div>
            @endforelse
        </div>
        <div class="mt-10">{{ $posts->links() }}</div>
    </div>
</x-layouts.site>
