@php($logo = $r->logo?->url())
<a href="{{ $r->publicUrl() }}" class="card group flex items-center gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-pop">
    @if ($logo)<img src="{{ $logo }}" alt="" width="56" height="56" loading="lazy" class="size-14 shrink-0 rounded-xl bg-surface-2 object-cover">
    @else<span class="grid size-14 shrink-0 place-items-center rounded-xl text-xl font-bold text-white" style="background: {{ $r->brandColor() }}">{{ mb_strtoupper(mb_substr($r->name, 0, 1)) }}</span>@endif
    <span class="min-w-0">
        <span class="display block truncate text-lg font-semibold group-hover:text-link">{{ $r->name }}</span>
        @if ($r->city || $r->cuisine)<span class="block truncate text-sm text-muted">{{ collect([$r->cuisine, $r->city])->filter()->implode(' · ') }}</span>@endif
        @if ($r->badgeKey())<span class="mt-1 inline-flex items-center gap-1 rounded-full bg-brand-100 px-2 py-0.5 text-xs font-semibold text-brand-900 dark:bg-brand-900/40 dark:text-brand-200">★ {{ __('platform.badge_'.$r->badgeKey()) }}</span>@endif
    </span>
</a>
