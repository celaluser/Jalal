<x-layouts.admin :title="__('admin.nav.subscriptions')">
    <form method="GET" class="mb-4 flex items-end gap-3">
        <x-ui.select name="status" :label="__('admin.status')" :value="$status" :placeholder="__('admin.all')" :options="collect(['trialing', 'active', 'past_due', 'canceled', 'expired'])->mapWithKeys(fn ($s) => [$s => __('admin.subscriptions.status_'.$s)])->all()" />
        <x-ui.button class="!w-auto">{{ __('admin.filter') }}</x-ui.button>
    </form>
    <x-ui.card class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-sm">
            <thead class="text-gray-500"><tr><th class="py-2 text-start">{{ __('admin.restaurants.name') }}</th><th class="text-start">{{ __('admin.restaurants.plan') }}</th><th class="text-start">{{ __('admin.status') }}</th><th class="text-start">{{ __('admin.subscriptions.period') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($subscriptions as $sub)
                <tr class="border-t border-gray-100 dark:border-gray-800">
                    <td class="py-2"><a class="text-brand-600 hover:underline" href="{{ route('admin.restaurants.show', $sub->restaurant_id) }}">{{ $restaurants[$sub->restaurant_id] ?? '#'.$sub->restaurant_id }}</a></td>
                    <td>{{ $sub->plan->name }}</td>
                    <td>{{ __('admin.subscriptions.status_'.$sub->status) }}@if ($sub->canceled_at && $sub->status === 'active') <span class="text-xs text-amber-600">({{ __('admin.subscriptions.cancels') }})</span>@endif</td>
                    <td>{{ $sub->starts_at?->toDateString() }} → {{ $sub->ends_at?->toDateString() ?? '∞' }}</td>
                    <td class="whitespace-nowrap text-end">
                        @if (in_array($sub->status, ['trialing', 'active', 'past_due'], true))
                            <form method="POST" action="{{ route('admin.subscriptions.renew', $sub) }}" class="inline">@csrf<button class="text-brand-600 hover:underline">{{ __('admin.subscriptions.renew') }}</button></form>
                            <form method="POST" action="{{ route('admin.subscriptions.cancel', $sub) }}" class="ms-2 inline" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf<input type="hidden" name="immediately" value="1"><button class="text-red-600 hover:underline">{{ __('admin.subscriptions.cancel_now') }}</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-4 text-gray-500">{{ __('admin.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $subscriptions->links() }}</div>
    </x-ui.card>
</x-layouts.admin>
