<div {{ $attributes->class('overflow-hidden rounded-2xl border border-line bg-surface shadow-card') }}>
    <div class="overflow-x-auto"><table class="table min-w-[560px]">{{ $slot }}</table></div>
    @if (isset($footer) && $footer->isNotEmpty())<div class="border-t border-line px-4 py-3">{{ $footer }}</div>@endif
</div>
