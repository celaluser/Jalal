@props(['name', 'size' => 8])
@php
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    // Stable colour per name, from the brand-adjacent palette only.
    $tones = ['bg-brand-200 text-brand-900', 'bg-accent-100 text-accent-900', 'bg-ink-200 text-ink-800', 'bg-blue-100 text-blue-900', 'bg-rose-100 text-rose-900'];
    $tone = $tones[crc32($name) % count($tones)];
@endphp
<span {{ $attributes->class(["size-{$size}", 'grid shrink-0 place-items-center rounded-full text-xs font-semibold', $tone]) }} aria-hidden="true">{{ $initials ?: '?' }}</span>
