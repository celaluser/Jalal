@props(['tone' => 'neutral', 'dot' => false])
<span {{ $attributes->class(['badge', "badge-{$tone}"]) }}>@if ($dot)<span class="size-1.5 rounded-full bg-current"></span>@endif{{ $slot }}</span>
