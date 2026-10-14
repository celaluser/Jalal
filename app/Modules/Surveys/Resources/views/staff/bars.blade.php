<ul class="mt-3 space-y-1.5">
    @foreach ($values as $label => $n)
        <li class="flex items-center gap-3 text-sm">
            <span class="w-24 shrink-0 truncate text-muted" title="{{ $label }}">{{ $label }}</span>
            <span class="h-2.5 flex-1 overflow-hidden rounded-full bg-surface-2"><span class="block h-full rounded-full bg-brand" style="width: {{ round($n / $total * 100) }}%"></span></span>
            <span class="w-8 shrink-0 text-end tnum">{{ $n }}</span>
        </li>
    @endforeach
</ul>
