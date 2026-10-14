<x-layouts.site :title="__('platform.directory_title')" :description="__('platform.directory_sub')">
    <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20">
        <div class="max-w-2xl"><p class="eyebrow text-accent-700 dark:text-accent-300">{{ __('platform.directory_eyebrow') }}</p><h1 class="display mt-3 text-4xl font-semibold sm:text-5xl">{{ __('platform.directory_title') }}</h1><p class="mt-3 text-lg text-muted">{{ __('platform.directory_sub') }}</p></div>
        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($restaurants as $r)
                @include('cms::site.restaurant-card', ['r' => $r])
            @empty
                <div class="card sm:col-span-2 lg:col-span-3"><x-ui.empty icon="store" :title="__('platform.directory_empty')" /></div>
            @endforelse
        </div>
    </div>
</x-layouts.site>
