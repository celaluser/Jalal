<x-layouts.site :title="$page->title" :description="$page->meta_description">
    <article class="mx-auto max-w-3xl px-4 py-14 sm:px-6 sm:py-20">
        <h1 class="display text-4xl font-semibold leading-[1.1] sm:text-5xl">{{ $page->title }}</h1>
        <div class="article mt-10">{!! markdown_safe($page->body) !!}</div>
    </article>
</x-layouts.site>
