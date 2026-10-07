<x-layouts.admin :title="__('admin.nav.subscriptions')">
    <x-ui.page-header :title="__('admin.nav.subscriptions')" :description="__('admin.subscriptions.description')" />
    <form method="GET" class="card flex flex-wrap items-end gap-3 p-4">
        <div class="w-48"><x-ui.select name="status" :label="__('admin.status')" :value="$status" :placeholder="__('admin.all')" :options="collect(['trialing', 'active', 'past_due', 'canceled', 'expired'])->mapWithKeys(fn ($s) => [$s => __('admin.subscriptions.status_'.$s)])->all()" /></div>
        <x-ui.button :block="false" icon="search">{{ __('admin.filter') }}</x-ui.button>
    </form>
    <x-ui.table>
        <thead><tr><th>{{ __('admin.restaurants.name') }}</th><th>{{ __('admin.restaurants.plan') }}</th><th>{{ __('admin.status') }}</th><th>{{ __('admin.subscriptions.period') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($subscriptions as $sub)
            <tr>
                <td><a class="link" href="{{ route('admin.restaurants.show', $sub->restaurant_id) }}">{{ $restaurants[$sub->restaurant_id] ?? '#'.$sub->restaurant_id }}</a></td>
                <td>{{ $sub->plan->name }}</td>
                <td><x-ui.status :value="$sub->status" :label="__('admin.subscriptions.status_'.$sub->status)" />@if ($sub->canceled_at && $sub->status === 'active')<span class="ms-1 text-xs text-muted">{{ __('admin.subscriptions.cancels') }}</span>@endif</td>
                <td class="tnum text-muted">{{ $sub->starts_at?->toDateString() }} → {{ $sub->ends_at?->toDateString() ?? '∞' }}</td>
                <td class="whitespace-nowrap text-end">
                    @if (in_array($sub->status, ['trialing', 'active', 'past_due'], true))
                        <form method="POST" action="{{ route('admin.subscriptions.renew', $sub) }}" class="inline">@csrf<button class="btn btn-ghost btn-sm"><x-ui.icon name="refresh" size="4" />{{ __('admin.subscriptions.renew') }}</button></form>
                        <form method="POST" action="{{ route('admin.subscriptions.cancel', $sub) }}" class="inline" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf<input type="hidden" name="immediately" value="1"><button class="btn btn-ghost btn-sm text-red-600 dark:text-red-400">{{ __('admin.subscriptions.cancel_now') }}</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr class="hover:!bg-transparent"><td colspan="5"><x-ui.empty icon="repeat" :title="__('admin.empty')" /></td></tr>
        @endforelse
        </tbody>
        <x-slot:footer>{{ $subscriptions->links() }}</x-slot:footer>
    </x-ui.table>
</x-layouts.admin>
