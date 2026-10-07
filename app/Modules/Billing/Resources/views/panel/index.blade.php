<x-layouts.app :title="__('billing.subscription')">
    <x-ui.page-header :title="__('billing.subscription')" :description="__('billing.subscription_sub')" />

    @if (session('instructions'))
        <x-ui.alert type="info"><strong>{{ __('billing.bank_title') }}</strong><br>{{ __('billing.bank_text', ['invoice' => session('instructions')['invoice']]) }}
            @if (session('instructions')['text'])<span class="mt-2 block whitespace-pre-line font-mono text-xs" dir="ltr">{{ session('instructions')['text'] }}</span>@endif
        </x-ui.alert>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        {{-- Current plan --}}
        <x-ui.card class="lg:col-span-1">
            <p class="eyebrow">{{ __('billing.current_plan') }}</p>
            @if ($subscription)
                <div class="mt-2 flex items-start justify-between gap-3">
                    <p class="display text-3xl font-semibold">{{ $plan->name }}</p>
                    <x-ui.status :value="$subscription->status" :label="__('admin.subscriptions.status_'.$subscription->status)" />
                </div>
                <p class="mt-1 text-sm text-muted">
                    @if ($subscription->canceled_at && $subscription->ends_at){{ __('billing.canceled_on') }} {{ $subscription->ends_at->toFormattedDateString() }}
                    @elseif ($subscription->ends_at){{ $subscription->status === 'trialing' ? __('billing.trial_until') : __('billing.renews_on') }} {{ $subscription->ends_at->toFormattedDateString() }}
                    @else{{ __('tenancy.no_end') }}@endif
                </p>
                @if (! $plan->isFree())<p class="tnum mt-3 text-sm"><bdi class="font-semibold">{{ number_format((float) $plan->price, 2) }} {{ $plan->currency_code }}</bdi> <span class="text-muted">/ {{ __('billing.interval_'.$plan->interval) }}</span></p>@endif
                @if (! $subscription->canceled_at && ! $plan->isFree())
                    <form method="POST" action="{{ route('billing.cancel') }}" class="mt-5" onsubmit="return confirm('{{ __('billing.cancel_confirm') }}')">@csrf
                        <button class="btn btn-ghost btn-sm text-red-600 dark:text-red-400">{{ __('billing.cancel_subscription') }}</button>
                    </form>
                @endif
            @else
                <x-ui.empty icon="layers" :title="__('billing.no_plan')" :text="__('tenancy.no_plan_text')" class="!py-6" />
            @endif
        </x-ui.card>

        {{-- Usage against the plan --}}
        <x-ui.card :title="__('billing.usage')" class="lg:col-span-2">
            @if ($usage)
                <ul class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                    @foreach ($usage as $row)
                        <li>
                            <div class="flex items-baseline justify-between text-sm">
                                <span class="font-medium">{{ __('admin.plans.limit_'.$row['key']) }}</span>
                                <span class="tnum text-muted"><bdi>
                                    @if ($row['limit'] === null){{ __('billing.usage_unlimited') }}
                                    @elseif ($row['used'] === null){{ __('billing.usage_limit_only', ['max' => $row['limit']]) }}
                                    @else{{ __('billing.usage_of', ['used' => $row['used'], 'max' => $row['limit']]) }}@endif
                                </bdi></span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-surface-2" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $row['percent'] ?? 0 }}" aria-label="{{ __('admin.plans.limit_'.$row['key']) }}">
                                @if ($row['percent'] !== null)<div @class(['h-full rounded-full', 'bg-accent-500' => $row['state'] === 'ok', 'bg-brand-500' => $row['state'] === 'warning', 'bg-red-500' => $row['state'] === 'full']) style="width: {{ max(2, $row['percent']) }}%"></div>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-muted">{{ __('tenancy.no_plan_text') }}</p>
            @endif
        </x-ui.card>
    </div>

    {{-- Plans --}}
    <h2 class="display mb-3 mt-8 text-xl font-semibold">{{ __('billing.plans') }}</h2>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($plans as $p)
            @php($isCurrent = $plan?->id === $p->id)
            <div @class(['card flex flex-col p-6', 'ring-2 ring-accent-600' => $isCurrent])>
                <div class="flex items-center justify-between"><h3 class="display text-lg font-semibold">{{ $p->name }}</h3>@if ($p->is_featured)<x-ui.badge tone="warning">{{ __('auth.popular') }}</x-ui.badge>@endif</div>
                <p class="mt-1 min-h-[2.5rem] text-sm text-muted">{{ $p->description }}</p>
                <p class="mt-3"><span class="display tnum text-3xl font-semibold"><bdi>{{ $p->isFree() ? __('site.pricing.free') : number_format((float) $p->price, fmod((float) $p->price, 1) == 0.0 ? 0 : 2).' '.$p->currency_code }}</bdi></span>@unless ($p->isFree())<span class="text-sm text-muted"> / {{ __('billing.interval_'.$p->interval) }}</span>@endunless</p>
                <ul class="mt-4 flex-1 space-y-1.5 text-sm">
                    @foreach (\App\Modules\Billing\Models\Plan::LIMITS as $limit)
                        <li class="flex gap-2"><x-ui.icon name="check" size="4" class="mt-0.5 shrink-0 text-accent-600" /><span>{{ __('admin.plans.limit_'.$limit) }}: <bdi class="font-medium">{{ $p->limit($limit) ?? __('site.pricing.unlimited') }}</bdi></span></li>
                    @endforeach
                </ul>
                @if ($isCurrent)
                    <span class="btn btn-secondary mt-6 w-full cursor-default opacity-70" aria-disabled="true">{{ __('billing.current') }}</span>
                @else
                    <form method="POST" action="{{ route('billing.select', $p->slug) }}" class="mt-6">@csrf
                        <button @class(['btn w-full', 'btn-primary' => $p->is_featured, 'btn-secondary' => ! $p->is_featured])>
                            {{ $p->trial_days > 0 && ! $p->isFree() && ! in_array($p->id, $triedTrial) ? __('billing.start_trial', ['days' => $p->trial_days]) : __('billing.choose') }}
                        </button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Invoices --}}
    <h2 class="display mb-3 mt-8 text-xl font-semibold">{{ __('billing.invoices') }}</h2>
    <x-ui.table>
        <thead><tr><th>{{ __('billing.invoice_number') }}</th><th>{{ __('billing.invoice_date') }}</th><th>{{ __('billing.invoice_total') }}</th><th>{{ __('admin.status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($invoices as $inv)
            <tr>
                <td class="font-medium" dir="ltr"><bdi>{{ $inv->number }}</bdi></td>
                <td class="tnum text-muted">{{ $inv->issued_at?->toFormattedDateString() }}</td>
                <td class="tnum"><bdi>{{ number_format((float) $inv->total, 2) }} {{ $inv->currency_code }}</bdi></td>
                <td><x-ui.status :value="$inv->status" :label="__('billing.status_'.$inv->status)" /></td>
                <td class="text-end"><a class="btn btn-ghost btn-sm" href="{{ route('billing.invoice.pdf', $inv->id) }}"><x-ui.icon name="download" size="4" />{{ __('billing.download_pdf') }}</a></td>
            </tr>
        @empty
            <tr class="hover:!bg-transparent"><td colspan="5" class="py-8 text-center text-muted">{{ __('billing.invoices_empty') }}</td></tr>
        @endforelse
        </tbody>
    </x-ui.table>
</x-layouts.app>
