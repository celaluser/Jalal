@props(['icon' => 'inbox', 'title', 'text' => null])
<div {{ $attributes->class('flex flex-col items-center px-6 py-14 text-center') }}>
    <div class="relative mb-5 grid size-20 place-items-center">
        <x-ui.qr-pattern :cells="13" :seed="3" class="absolute inset-0 size-full text-ink-200 dark:text-ink-800" />
        <span class="relative grid size-11 place-items-center rounded-2xl bg-surface text-muted shadow-card ring-1 ring-line"><x-ui.icon :name="$icon" size="5" /></span>
    </div>
    <p class="font-semibold">{{ $title }}</p>
    @if ($text)<p class="mt-1 max-w-sm text-sm text-muted">{{ $text }}</p>@endif
    @if ($slot->isNotEmpty())<div class="mt-5">{{ $slot }}</div>@endif
</div>
