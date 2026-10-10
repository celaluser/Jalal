@php($money = fn ($amount) => \App\Modules\Core\Models\Currency::where('code', $item['currency'])->first()?->format($amount) ?? number_format($amount, 2).' '.$item['currency'])
<x-layouts.app :title="$item['name']">
    <x-ui.page-header :title="$item['name']" :back="['url' => route('store.index', ['tab' => $item['kind'] === 'theme' ? 'themes' : 'features']), 'label' => __('store.title')]">
        <x-slot:actions>@include('store::panel.badge', ['item' => $item])</x-slot:actions>
    </x-ui.page-header>
    @if (session('status'))<x-ui.alert type="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif
    @if (session('instructions'))<x-ui.alert type="info" class="mb-4"><strong>{{ session('instructions')['invoice'] }}</strong><br>{!! nl2br(e(session('instructions')['text'])) !!}</x-ui.alert>@endif
    @error('checkout')<x-ui.alert type="error" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    <div class="grid gap-5 lg:grid-cols-5">
        <div class="space-y-5 lg:col-span-3">
            <x-ui.card :title="__('store.about')">
                @if ($item['summary'])<p class="font-medium">{{ $item['summary'] }}</p>@endif
                @if ($item['description'])<div class="mt-2 whitespace-pre-line text-sm text-muted">{{ $item['description'] }}</div>@endif
                @if (! $item['summary'] && ! $item['description'])<p class="text-sm text-muted">{{ __('store.no_description') }}</p>@endif
                @if ($item['kind'] === 'theme')<a href="{{ route('appearance.edit') }}" class="btn btn-secondary btn-sm mt-4">{{ __('store.open_appearance') }}</a>@endif
                @if ($item['key'] === 'custom_domain' || $item['key'] === 'subdomain')<a href="{{ route('domains.index') }}" class="btn btn-secondary btn-sm mt-4">{{ __('store.open_domains') }}</a>@endif
            </x-ui.card>
            @if ($owned)
                <x-ui.card :title="__('store.yours')">
                    <p class="text-sm">{{ $owned->ends_at ? __('store.valid_until', ['date' => $owned->ends_at->isoFormat('LL')]) : __('store.valid_forever') }}
                        <span class="text-muted">· {{ __('store.source_'.$owned->source) }}</span></p>
                </x-ui.card>
            @endif
        </div>

        <div class="lg:col-span-2">
            @if ($item['state']['status'] === 'buy' || ($item['state']['status'] === 'owned' && $item['billing'] !== 'one_time' && $item['mode'] === 'paid'))
                @can('billing.manage')
                    <form method="POST" action="{{ route('store.buy', $item['slug']) }}" class="lg:sticky lg:top-6" x-data="{ units: {{ (int) old('units', array_key_first($units->all())) }}, prices: @js($units->map(fn ($q) => $money($q['price']))->all()) }">
                        @csrf
                        <x-ui.card :title="$item['state']['status'] === 'owned' ? __('store.extend') : __('store.get_it')">
                            <p class="display text-2xl font-semibold tnum"><bdi x-text="prices[units]"></bdi></p>
                            <p class="text-sm text-muted">{{ __('store.per_'.$item['billing']) }}@if ($item['billing'] !== 'one_time') · {{ __('store.renew_note') }}@endif</p>
                            @if (count($units) > 1)
                                <div class="mt-4"><label for="units" class="mb-1.5 block text-sm font-medium">{{ $item['billing'] === 'monthly' ? __('store.how_many_months') : __('store.how_many_years') }}</label>
                                    <select id="units" name="units" class="field" x-model.number="units">@foreach ($units as $u => $q)<option value="{{ $u }}">{{ $item['billing'] === 'monthly' ? trans_choice('store.n_months', $u, ['count' => $u]) : trans_choice('store.n_years', $u, ['count' => $u]) }}</option>@endforeach</select></div>
                            @endif
                            <div class="mt-4 grid gap-2" role="radiogroup" aria-label="{{ __('billing.payment_method') }}">
                                @forelse ($gateways as $code => $gateway)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line-strong bg-surface p-3 text-sm has-[:checked]:border-accent-600"><input type="radio" name="gateway" value="{{ $code }}" @checked(old('gateway', array_key_first($gateways)) === $code) required><span class="font-medium">{{ $gateway->name() }}</span></label>
                                @empty
                                    <p class="text-sm text-muted">{{ __('billing.no_gateways_text') }}</p>
                                @endforelse
                            </div>
                            <p class="mt-3 text-xs text-muted">{{ __('store.tax_note') }}</p>
                            <button class="btn btn-primary btn-lg mt-4 w-full" @disabled(! count($gateways))>{{ $item['billing'] === 'one_time' ? __('store.buy') : ($item['state']['status'] === 'owned' ? __('store.extend') : __('store.rent')) }}</button>
                        </x-ui.card>
                    </form>
                    @if ($canTrial)
                        <form method="POST" action="{{ route('store.trial', $item['slug']) }}" class="mt-3">@csrf<button class="btn btn-secondary w-full">{{ trans_choice('store.try_days', $item['trial_days'], ['count' => $item['trial_days']]) }}</button></form>
                    @endif
                @else
                    <x-ui.alert type="info">{{ __('store.owner_only') }}</x-ui.alert>
                @endcan
            @elseif ($item['state']['status'] === 'upgrade')
                <x-ui.card :title="__('store.state_upgrade')"><p class="text-sm text-muted">{{ __('store.upgrade_text') }}</p><a href="{{ route('billing.index') }}" class="btn btn-primary mt-4">{{ __('store.see_plans') }}</a></x-ui.card>
            @else
                <x-ui.card><p class="text-sm">{{ __('store.included_text') }}</p></x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.app>
