        @if (collect([$marketing['link_instagram'], $marketing['link_facebook'], $marketing['link_website']])->filter()->isNotEmpty())
            <section><h2>{{ __('customer.about_follow') }}</h2>
                @foreach (['link_instagram' => 'Instagram', 'link_facebook' => 'Facebook', 'link_website' => __('customer.about_website')] as $k => $label)@if ($marketing[$k])<a class="btn alt" href="{{ $marketing[$k] }}" rel="noopener" target="_blank">{{ $label }}</a>@endif @endforeach
            </section>
        @endif
