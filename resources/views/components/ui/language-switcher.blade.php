@if (count($activeLanguages ?? []) > 1)
    <div class="flex items-center gap-0.5 rounded-lg bg-surface-2 p-0.5 text-xs font-semibold" role="group" aria-label="{{ __('ui.language') }}">
        @foreach ($activeLanguages as $code => $rtl)
            <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" hreflang="{{ $code }}"
               @class(['inline-flex items-center rounded-md px-2.5 py-1.5 uppercase transition', 'bg-surface text-fg shadow-sm' => app()->getLocale() === $code, 'text-muted hover:text-fg' => app()->getLocale() !== $code])
               @if (app()->getLocale() === $code) aria-current="true" @endif>{{ $code }}</a>
        @endforeach
    </div>
@endif
