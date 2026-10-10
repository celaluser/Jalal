<x-layouts.admin :title="__('admin.nav.tickets')">
    <x-ui.page-header :title="__('admin.nav.tickets')" :description="__('admin.tickets.description')" />
    <form method="GET" class="card flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-[14rem] flex-1"><x-ui.input name="q" :value="$filters['q']" :label="__('admin.search')" /></div>
        <div class="w-40"><x-ui.select name="status" :label="__('admin.status')" :value="$filters['status']" :placeholder="__('admin.all')" :options="collect(\App\Modules\Support\Models\Ticket::STATUSES)->mapWithKeys(fn ($s) => [$s => __('support.status_'.$s)])->all()" /></div>
        <div class="w-40"><x-ui.select name="priority" :label="__('support.priority')" :value="$filters['priority']" :placeholder="__('admin.all')" :options="collect(\App\Modules\Support\Models\Ticket::PRIORITIES)->mapWithKeys(fn ($s) => [$s => __('support.priority_'.$s)])->all()" /></div>
        <x-ui.button :block="false" icon="search">{{ __('admin.filter') }}</x-ui.button>
    </form>
    <x-ui.table>
        <thead><tr><th>{{ __('support.subject') }}</th><th>{{ __('admin.restaurants.name') }}</th><th>{{ __('support.priority') }}</th><th>{{ __('admin.status') }}</th><th>{{ __('support.last_activity') }}</th></tr></thead>
        <tbody>
        @forelse ($tickets as $ticket)
            <tr>
                <td><a class="link" href="{{ route('admin.tickets.show', $ticket->id) }}">{{ $ticket->subject }}</a></td>
                <td class="text-muted">{{ $restaurants[$ticket->restaurant_id] ?? '#'.$ticket->restaurant_id }}</td>
                <td>@if ($ticket->priority === 'high')<x-ui.status value="high" :label="__('support.priority_high')" />@else<span class="text-muted">{{ __('support.priority_'.$ticket->priority) }}</span>@endif</td>
                <td><x-ui.status :value="$ticket->status" :label="__('support.status_'.$ticket->status)" /></td>
                <td class="text-muted">{{ $ticket->last_reply_at?->diffForHumans() }}</td>
            </tr>
        @empty
            <tr class="hover:!bg-transparent"><td colspan="5"><x-ui.empty icon="life-buoy" :title="__('admin.empty')" /></td></tr>
        @endforelse
        </tbody>
        <x-slot:footer>{{ $tickets->links() }}</x-slot:footer>
    </x-ui.table>
</x-layouts.admin>
