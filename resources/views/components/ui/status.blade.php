@props(['value', 'label' => null])
@php
    // One mapping for every status in the product, so the same word always has the same colour.
    $tones = [
        'active' => 'success', 'paid' => 'success', 'published' => 'success', 'live' => 'success', 'ready' => 'success', 'succeeded' => 'success',
        'answered' => 'info', 'trialing' => 'info', 'scheduled' => 'info',
        'open' => 'warning', 'past_due' => 'warning', 'pending' => 'warning', 'incomplete' => 'warning',
        'suspended' => 'danger', 'failed' => 'danger', 'mismatch' => 'danger', 'deleted' => 'danger', 'high' => 'danger',
    ];
@endphp
<x-ui.badge :tone="$tones[$value] ?? 'neutral'" dot>{{ $label ?? \Illuminate\Support\Str::headline($value) }}</x-ui.badge>
