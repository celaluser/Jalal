<x-layouts.admin :title="__('admin.nav.plans')">
    <div class="mb-4 flex justify-end"><a href="{{ route('admin.plans.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('admin.plans.new') }}</a></div>
    <x-ui.card class="overflow-x-auto">
        <table class="w-full min-w-[560px] text-sm">
            <thead class="text-gray-500"><tr><th class="py-2 text-start">{{ __('admin.plans.name') }}</th><th class="text-start">{{ __('admin.plans.price') }}</th><th class="text-start">{{ __('admin.plans.trial') }}</th><th class="text-start">{{ __('admin.plans.subscribers') }}</th><th class="text-start">{{ __('admin.status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($plans as $plan)
                <tr class="border-t border-gray-100 dark:border-gray-800">
                    <td class="py-2 font-medium">{{ $plan->name }}@if ($plan->is_featured) <span class="text-xs text-brand-600">★</span>@endif</td>
                    <td>{{ $plan->interval === 'free' ? __('billing.interval_free') : number_format((float) $plan->price, 2).' '.$plan->currency_code.' / '.__('billing.interval_'.$plan->interval) }}</td>
                    <td>{{ $plan->trial_days ?: '—' }}</td>
                    <td>{{ $plan->subscriptions_count }}</td>
                    <td>{{ $plan->is_active ? __('admin.active') : __('admin.inactive') }}</td>
                    <td class="text-end"><a class="text-brand-600 hover:underline" href="{{ route('admin.plans.edit', $plan) }}">{{ __('admin.edit') }}</a>
                        <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="inline" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf @method('DELETE')<button class="ms-2 text-red-600 hover:underline">{{ __('admin.delete') }}</button></form></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-4 text-gray-500">{{ __('admin.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.card>
</x-layouts.admin>
