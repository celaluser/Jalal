@if (count($activeLanguages ?? []) > 1)
    <div class="flex items-center gap-2">
        @foreach ($activeLanguages as $code => $rtl)
            <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}"
               @class(['uppercase', 'font-semibold text-brand-600' => app()->getLocale() === $code])>{{ $code }}</a>
        @endforeach
    </div>
@endif
