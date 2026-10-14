        @if ($restaurant->address || $restaurant->city || $restaurant->phone)
            <section><h2>{{ __('customer.about_contact') }}</h2>
                <p>{{ collect([$restaurant->address, $restaurant->city, $restaurant->country])->filter()->implode(', ') }}@if ($restaurant->phone)<br><span dir="ltr">{{ $restaurant->phone }}</span>@endif</p>
            </section>
        @endif
