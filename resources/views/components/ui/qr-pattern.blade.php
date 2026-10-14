@props(['cells' => 25, 'seed' => 7])
@php
    // A deterministic, decorative QR-like grid (finder squares in three corners, scattered modules).
    // It encodes nothing: it is texture, so it is hidden from assistive tech.
    $rects = [];
    $state = $seed;
    $rand = function () use (&$state) { $state = ($state * 1103515245 + 12345) & 0x7fffffff; return $state / 0x7fffffff; };
    $inFinder = fn ($x, $y) => ($x < 8 && $y < 8) || ($x >= $cells - 8 && $y < 8) || ($x < 8 && $y >= $cells - 8);
    for ($y = 0; $y < $cells; $y++) {
        for ($x = 0; $x < $cells; $x++) {
            if (! $inFinder($x, $y) && $rand() > 0.58) {
                $rects[] = [$x, $y];
            }
        }
    }
@endphp
<svg {{ $attributes }} viewBox="0 0 {{ $cells }} {{ $cells }}" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    @foreach ([[0, 0], [$cells - 7, 0], [0, $cells - 7]] as [$ox, $oy])
        {{-- Finder pattern: ring + centre dot, like a real QR corner --}}
        <rect x="{{ $ox + 0.5 }}" y="{{ $oy + 0.5 }}" width="6" height="6" rx="1.6" fill="none" stroke="currentColor" stroke-width="1"/>
        <rect x="{{ $ox + 2.1 }}" y="{{ $oy + 2.1 }}" width="2.8" height="2.8" rx="0.8"/>
    @endforeach
    @foreach ($rects as [$x, $y])
        <rect x="{{ $x + 0.12 }}" y="{{ $y + 0.12 }}" width="0.76" height="0.76" rx="0.22"/>
    @endforeach
</svg>
