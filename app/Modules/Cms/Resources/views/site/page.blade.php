<x-layouts.site :title="$page->title" :description="$page->meta_description">
    <article class="mx-auto max-w-3xl px-4 py-12">
        <h1 class="text-4xl font-extrabold">{{ $page->title }}</h1>
        <div class="mt-8 space-y-4 leading-relaxed [&_a]:text-brand-600 [&_a]:underline [&_h2]:mt-8 [&_h2]:text-2xl [&_h2]:font-bold [&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-semibold [&_li]:ms-5 [&_ol]:list-decimal [&_ul]:list-disc">{!! markdown_safe($page->body) !!}</div>
    </article>
</x-layouts.site>
