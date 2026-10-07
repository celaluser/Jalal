@props(['size' => 8])
{{-- The product mark: a QR code reduced to three finder squares and one module. --}}
<svg {{ $attributes->class(["size-{$size}", 'shrink-0']) }} viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <rect width="32" height="32" rx="8" class="fill-brand-500"/>
    <rect x="6" y="6" width="8" height="8" rx="2.2" class="fill-ink-950"/>
    <rect x="18" y="6" width="8" height="8" rx="2.2" class="fill-ink-950"/>
    <rect x="6" y="18" width="8" height="8" rx="2.2" class="fill-ink-950"/>
    <rect x="18" y="18" width="3.6" height="3.6" rx="1" class="fill-ink-950"/>
    <rect x="22.4" y="22.4" width="3.6" height="3.6" rx="1" class="fill-ink-950"/>
    <rect x="9" y="9" width="2" height="2" rx=".6" class="fill-brand-500"/>
    <rect x="21" y="9" width="2" height="2" rx=".6" class="fill-brand-500"/>
    <rect x="9" y="21" width="2" height="2" rx=".6" class="fill-brand-500"/>
</svg>
