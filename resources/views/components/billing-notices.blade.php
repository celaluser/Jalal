{{-- Subscription banners for the restaurant owner: no plan, trial ending, overdue, limits. --}}
@php($restaurant = auth()->user()?->restaurant)
@if ($restaurant && ! request()->routeIs('billing.*', 'onboarding.*'))
    @foreach (app(\App\Modules\Billing\Services\PlanNotices::class)->for($restaurant) as $notice)
        <x-ui.alert :type="$notice['level']">
            <span class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                <span>{{ $notice['text'] }}</span>
                @if ($notice['cta'])<a href="{{ route('billing.index') }}" class="font-semibold underline underline-offset-2">{{ $notice['cta'] }}</a>@endif
            </span>
        </x-ui.alert>
    @endforeach
@endif
