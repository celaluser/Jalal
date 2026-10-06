@php
    $seoTitle = platform_setting('seo.meta_title');
@endphp
<x-layouts.site :title="$seoTitle" :description="$c['hero']['subtitle']">
    {{-- Hero --}}
    <section class="bg-gradient-to-b from-brand-50 to-white py-20 dark:from-gray-900 dark:to-gray-950">
        <div class="mx-auto max-w-4xl px-4 text-center">
            <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl">{{ $c['hero']['title'] }}</h1>
            <p class="mx-auto mt-5 max-w-2xl text-lg text-gray-600 dark:text-gray-300">{{ $c['hero']['subtitle'] }}</p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ $registration ? route('register') : route('login') }}" class="rounded-xl bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ $c['hero']['cta_label'] }}</a>
                @if (! empty($c['hero']['secondary_label']))
                    <a href="#pricing" class="rounded-xl bg-white px-6 py-3 font-semibold ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-900 dark:ring-gray-700">{{ $c['hero']['secondary_label'] }}</a>
                @endif
            </div>
        </div>
    </section>

    {{-- Features --}}
    @if (! empty($c['features']))
        <section id="features" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-16">
            <h2 class="sr-only">{{ __('site.nav.features') }}</h2>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($c['features'] as $feature)
                    <x-ui.card>
                        <h3 class="font-semibold">{{ $feature['title'] }}</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $feature['text'] }}</p>
                    </x-ui.card>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Pricing, straight from the active plans --}}
    @if ($plans->isNotEmpty())
        <section id="pricing" class="scroll-mt-20 bg-gray-100 py-16 dark:bg-gray-900">
            <div class="mx-auto max-w-6xl px-4">
                <div class="mb-10 text-center">
                    <h2 class="text-3xl font-bold">{{ $c['pricing']['title'] }}</h2>
                    <p class="mt-2 text-gray-600 dark:text-gray-300">{{ $c['pricing']['subtitle'] }}</p>
                </div>
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($plans as $plan)
                        <div @class(['flex flex-col rounded-2xl bg-white p-6 dark:bg-gray-950', 'ring-2 ring-brand-600' => $plan->is_featured, 'ring-1 ring-gray-200 dark:ring-gray-800' => ! $plan->is_featured])>
                            @if ($plan->is_featured)<span class="mb-2 w-fit rounded-full bg-brand-100 px-2 py-0.5 text-xs font-semibold text-brand-700">{{ __('site.pricing.popular') }}</span>@endif
                            <h3 class="text-lg font-semibold">{{ $plan->name }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ $plan->description }}</p>
                            <p class="mt-4 text-3xl font-extrabold">
                                @if ($plan->isFree()){{ __('site.pricing.free') }}@else{{ number_format((float) $plan->price, 2) }} <span class="text-base font-medium">{{ $plan->currency_code }}</span>@endif
                                @if (! $plan->isFree() && $plan->interval !== 'lifetime')<span class="text-sm font-normal text-gray-500">/ {{ __('billing.interval_'.$plan->interval) }}</span>@endif
                                @if ($plan->interval === 'lifetime')<span class="text-sm font-normal text-gray-500">{{ __('site.pricing.one_time') }}</span>@endif
                            </p>
                            @if ($plan->trial_days > 0)<p class="mt-1 text-sm text-brand-600">{{ __('site.pricing.trial', ['days' => $plan->trial_days]) }}</p>@endif
                            <ul class="mt-5 flex-1 space-y-2 text-sm">
                                @foreach (\App\Modules\Billing\Models\Plan::LIMITS as $limit)
                                    <li>✓ {{ __('admin.plans.limit_'.$limit) }}: <strong>{{ $plan->limit($limit) ?? __('site.pricing.unlimited') }}</strong></li>
                                @endforeach
                                @foreach (\App\Modules\Billing\Models\Plan::FEATURES as $feature)
                                    @if ($plan->hasFeature($feature))<li>✓ {{ __('admin.plans.feature_'.$feature) }}</li>@endif
                                @endforeach
                            </ul>
                            <a href="{{ $registration ? route('register', ['plan' => $plan->slug]) : route('login') }}" class="mt-6 block rounded-xl bg-brand-600 py-2.5 text-center font-semibold text-white hover:bg-brand-700">{{ $plan->trial_days > 0 ? __('site.pricing.start_trial') : __('site.pricing.choose') }}</a>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Testimonials --}}
    @if (! empty($c['testimonials']))
        <section class="mx-auto max-w-6xl px-4 py-16">
            <div class="grid gap-6 md:grid-cols-3">
                @foreach ($c['testimonials'] as $t)
                    <figure class="rounded-2xl bg-white p-6 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                        <blockquote class="text-gray-700 dark:text-gray-200">“{{ $t['quote'] }}”</blockquote>
                        <figcaption class="mt-4 text-sm"><strong>{{ $t['name'] }}</strong>@if (! empty($t['role'])) <span class="text-gray-500">· {{ $t['role'] }}</span>@endif</figcaption>
                    </figure>
                @endforeach
            </div>
        </section>
    @endif

    {{-- FAQ --}}
    @if (! empty($c['faq']))
        <section id="faq" class="mx-auto max-w-3xl scroll-mt-20 px-4 py-16">
            <h2 class="mb-6 text-center text-3xl font-bold">{{ __('site.nav.faq') }}</h2>
            <div class="space-y-3">
                @foreach ($c['faq'] as $item)
                    <details class="rounded-xl bg-white p-4 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                        <summary class="cursor-pointer font-medium">{{ $item['question'] }}</summary>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $item['answer'] }}</p>
                    </details>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Contact --}}
    @php($contact = $c['contact'])
    @if (! empty($contact['email']) || ! empty($contact['phone']) || ! empty($contact['address']))
        <section id="contact" class="bg-brand-600 py-14 text-white">
            <div class="mx-auto max-w-3xl px-4 text-center">
                <h2 class="text-2xl font-bold">{{ $contact['title'] }}</h2>
                <p class="mt-2 opacity-90">{{ $contact['text'] }}</p>
                <p class="mt-4 space-x-4 text-sm font-medium">
                    @if (! empty($contact['email']))<a class="underline" href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>@endif
                    @if (! empty($contact['phone']))<a class="underline" href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a>@endif
                </p>
                @if (! empty($contact['address']))<p class="mt-2 text-sm opacity-90">{{ $contact['address'] }}</p>@endif
            </div>
        </section>
    @endif
</x-layouts.site>
