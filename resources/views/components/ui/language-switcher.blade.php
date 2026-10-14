@if (count($activeLanguages ?? []) > 1)
    @php($current = app()->getLocale())
    @if (count($activeLanguages) <= 4)
        {{-- A few languages: all of them as buttons --}}
        <div class="flex items-center gap-0.5 rounded-lg bg-surface-2 p-0.5 text-xs font-semibold" role="group" aria-label="{{ __('ui.language') }}">
            @foreach ($activeLanguages as $code => $rtl)
                <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" hreflang="{{ $code }}" lang="{{ $code }}" title="{{ $languageNames[$code] ?? strtoupper($code) }}" aria-label="{{ strtoupper($code) }} · {{ $languageNames[$code] ?? strtoupper($code) }}"
                   @class(['inline-flex items-center rounded-md px-2.5 py-1.5 uppercase transition', 'bg-surface text-fg shadow-sm' => $current === $code, 'text-muted hover:text-fg' => $current !== $code])
                   @if ($current === $code) aria-current="true" @endif>{{ $code }}</a>
            @endforeach
        </div>
    @else
        {{-- Many languages: one button that opens the list, each language written in its own language --}}
        <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-surface-2 px-2.5 py-1.5 text-xs font-semibold uppercase text-fg transition hover:bg-line" x-on:click="open = !open" :aria-expanded="open" aria-haspopup="true" aria-label="{{ __('ui.language') }}: {{ $languageNames[$current] ?? strtoupper($current) }}">
                <x-ui.icon name="globe" size="4" />{{ $current }}<x-ui.icon name="chevron-down" size="4" class="text-muted" />
            </button>
            <ul x-show="open" x-cloak x-transition.origin.top class="absolute end-0 z-50 mt-2 grid max-h-80 w-56 gap-0.5 overflow-y-auto rounded-xl border border-line bg-surface p-1.5 text-sm font-medium normal-case shadow-pop">
                @foreach ($activeLanguages as $code => $rtl)
                    <li><a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" hreflang="{{ $code }}" lang="{{ $code }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}"
                           @class(['flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-fg transition hover:bg-surface-2', 'bg-surface-2 font-semibold' => $current === $code])
                           @if ($current === $code) aria-current="true" @endif><span>{{ $languageNames[$code] ?? strtoupper($code) }}</span><span class="text-xs uppercase text-muted" dir="ltr">{{ $code }}</span></a></li>
                @endforeach
            </ul>
        </div>
    @endif
@endif
