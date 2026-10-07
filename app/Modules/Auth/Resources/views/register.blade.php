<x-layouts.guest :title="__('auth.register')" :heading="__('auth.register_heading')" :subheading="__('auth.register_sub')">
    <form method="POST" action="{{ url('/register') }}" class="space-y-4">
        @csrf
        @if ($plans->isNotEmpty())
            <fieldset>
                <legend class="mb-2 text-sm font-medium">{{ __('auth.choose_plan') }}</legend>
                <div class="grid gap-2">
                    @foreach ($plans as $plan)
                        <label class="relative flex cursor-pointer items-center gap-3 rounded-xl border border-line-strong bg-surface p-3.5 transition hover:bg-surface-2 has-[:checked]:border-accent-600 has-[:checked]:bg-accent-50 has-[:checked]:ring-2 has-[:checked]:ring-accent-600/20 dark:has-[:checked]:bg-accent-900/20">
                            <input type="radio" name="plan" value="{{ $plan->slug }}" class="size-4 accent-[var(--color-accent-600)]" @checked($selected?->is($plan))>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2 font-semibold">{{ $plan->name }}@if ($plan->is_featured)<x-ui.badge tone="warning">{{ __('auth.popular') }}</x-ui.badge>@endif</span>
                                <span class="block text-xs text-muted">@if ($plan->trial_days > 0 && ! $plan->isFree()){{ __('auth.trial_days', ['days' => $plan->trial_days]) }}@elseif ($plan->isFree()){{ __('auth.no_card') }}@else{{ __('auth.pay_after_verify') }}@endif</span>
                            </span>
                            <span class="tnum text-end text-sm font-semibold"><bdi>{{ $plan->isFree() ? __('site.pricing.free') : number_format((float) $plan->price, fmod((float) $plan->price, 1) == 0.0 ? 0 : 2).' '.$plan->currency_code }}</bdi>@unless ($plan->isFree())<span class="block text-xs font-normal text-muted">/ {{ __('billing.interval_'.$plan->interval) }}</span>@endunless</span>
                        </label>
                    @endforeach
                </div>
                @error('plan')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
            </fieldset>
        @endif
        <x-ui.input name="restaurant_name" :label="__('auth.restaurant_name')" required autofocus />
        <x-ui.input name="name" :label="__('auth.name')" required autocomplete="name" />
        <x-ui.input name="email" type="email" :label="__('auth.email')" required autocomplete="username" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="password" type="password" :label="__('auth.password_label')" required autocomplete="new-password" :hint="__('auth.password_hint')" />
            <x-ui.input name="password_confirmation" type="password" :label="__('auth.password_confirm')" required autocomplete="new-password" />
        </div>
        <x-recaptcha />
        <x-ui.button size="lg">{{ __('auth.register') }}</x-ui.button>
    </form>
    <p class="text-center text-sm text-muted">{{ __('auth.have_account') }} <a class="link" href="{{ route('login') }}">{{ __('auth.login') }}</a></p>
</x-layouts.guest>
