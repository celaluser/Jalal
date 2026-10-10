@props(['pad' => true, 'title' => null, 'description' => null])
<div {{ $attributes->class(['card', 'card-pad' => $pad && ! $title]) }}>
    @if ($title)
        <div class="border-b border-line px-5 py-4 sm:px-6">
            <h2 class="text-[15px] font-semibold">{{ $title }}</h2>
            @if ($description)<p class="mt-0.5 text-sm text-muted">{{ $description }}</p>@endif
        </div>
        <div @class(['card-pad' => $pad])>{{ $slot }}</div>
    @else
        {{ $slot }}
    @endif
</div>
