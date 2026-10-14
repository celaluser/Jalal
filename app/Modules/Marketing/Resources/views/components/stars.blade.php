@props(['rating', 'size' => 4])
{{-- Five stars, filled up to the rating. Announced as text, not as five separate images. --}}
<span {{ $attributes->class('inline-flex items-center gap-0.5 text-brand-500') }} role="img" aria-label="{{ trans_choice('marketing.stars', max(1, (int) round($rating)), ['count' => (int) round($rating)]) }}">
    @for ($i = 1; $i <= 5; $i++)
        <svg class="size-{{ $size }} {{ $i <= round($rating) ? 'fill-current' : 'fill-none text-ink-300 dark:text-ink-700' }}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
    @endfor
</span>
