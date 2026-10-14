<x-layouts.app :title="__('marketing.banners_title')">
    <x-ui.page-header :title="__('marketing.banners_title')" :description="__('marketing.banners_sub')">
        <x-slot:actions><a href="{{ route('banners.create') }}" class="btn btn-primary"><x-ui.icon name="plus" size="4" />{{ __('marketing.new_banner') }}</a></x-slot:actions>
    </x-ui.page-header>
    @php($restaurant = auth()->user()->restaurant)
    @if ($banners->isEmpty())
        <div class="card"><x-ui.empty icon="image" :title="__('marketing.banners_empty')" :text="__('marketing.banners_empty_text')" /></div>
    @else
        <ul class="grid gap-3">
            @foreach ($banners as $b)
                <li class="card flex flex-wrap items-center gap-4 p-4">
                    <div class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-xl bg-surface-2 ring-1 ring-line">@if ($b->image)<img src="{{ $b->image->thumbUrl() }}" alt="" class="size-full object-cover">@else<x-ui.icon name="image" size="5" class="text-muted" />@endif</div>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 font-semibold">{{ $b->tr('title', app()->getLocale(), $restaurant->locale) }}
                            @if ($b->is_popup)<x-ui.badge tone="info">{{ __('marketing.popup') }}</x-ui.badge>@endif
                            <x-ui.badge :tone="$b->is_active ? 'success' : 'neutral'" dot>{{ $b->is_active ? __('marketing.status_active') : __('marketing.status_off') }}</x-ui.badge></p>
                        <p class="truncate text-sm text-muted">{{ $b->starts_on?->toFormattedDateString() ?? '—' }} → {{ $b->ends_on?->toFormattedDateString() ?? '—' }}</p>
                    </div>
                    <a href="{{ route('banners.edit', $b->id) }}" class="btn btn-ghost btn-sm" aria-label="{{ __('admin.edit') }}"><x-ui.icon name="pen" size="4" /></a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
