@props(['title', 'description' => null, 'back' => null])
<div {{ $attributes->class('mb-6 flex flex-wrap items-end justify-between gap-4') }}>
    <div class="min-w-0">
        @if ($back)<a href="{{ $back['url'] }}" class="mb-2 inline-flex items-center gap-1 text-sm text-muted hover:text-fg"><x-ui.icon name="chevron-right" size="4" class="rotate-180 rtl:rotate-0" />{{ $back['label'] }}</a>@endif
        <h1 class="display text-2xl font-semibold sm:text-[28px]">{{ $title }}</h1>
        @if ($description)<p class="mt-1 max-w-2xl text-sm text-muted">{{ $description }}</p>@endif
    </div>
    @if (isset($actions) && $actions->isNotEmpty())<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endif
</div>
