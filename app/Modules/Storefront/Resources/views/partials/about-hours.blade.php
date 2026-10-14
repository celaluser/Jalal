        @if (collect($s['hours'])->filter()->isNotEmpty())
            <section><h2>{{ __('customer.about_hours') }}</h2>
                <table>@foreach (range(0, 6) as $i)@if (! empty($s['hours'][$i]))<tr><td>{{ __('customer.about_day_'.$i) }}</td><td dir="ltr">{{ $s['hours'][$i] }}</td></tr>@endif @endforeach</table>
            </section>
        @endif
