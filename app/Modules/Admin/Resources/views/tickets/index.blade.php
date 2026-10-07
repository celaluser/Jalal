<x-layouts.admin :title="__('admin.nav.tickets')">
    <x-ui.card class="mb-4">
        <form method="GET" class="grid gap-3 sm:grid-cols-4">
            <x-ui.input name="q" :value="$filters['q']" :label="__('admin.search')" />
            <x-ui.select name="status" :label="__('admin.status')" :value="$filters['status']" :placeholder="__('admin.all')" :options="collect(\App\Modules\Support\Models\Ticket::STATUSES)->mapWithKeys(fn ($s) => [$s => __('support.status_'.$s)])->all()" />
            <x-ui.select name="priority" :label="__('support.priority')" :value="$filters['priority']" :placeholder="__('admin.all')" :options="collect(\App\Modules\Support\Models\Ticket::PRIORITIES)->mapWithKeys(fn ($s) => [$s => __('support.priority_'.$s)])->all()" />
            <div class="flex items-end"><x-ui.button class="!w-auto">{{ __('admin.filter') }}</x-ui.button></div>
        </form>
    </x-ui.card>
    <x-ui.card class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-sm">
            <thead class="text-gray-500"><tr><th class="py-2 text-start">{{ __('support.subject') }}</th><th class="text-start">{{ __('admin.restaurants.name') }}</th><th class="text-start">{{ __('support.priority') }}</th><th class="text-start">{{ __('admin.status') }}</th><th class="text-start">{{ __('support.last_activity') }}</th></tr></thead>
            <tbody>
            @forelse ($tickets as $ticket)
                <tr class="border-t border-gray-100 dark:border-gray-800">
                    <td class="py-2"><a class="font-medium text-brand-600 hover:underline" href="{{ route('admin.tickets.show', $ticket->id) }}">{{ $ticket->subject }}</a></td>
                    <td>{{ $restaurants[$ticket->restaurant_id] ?? '#'.$ticket->restaurant_id }}</td>
                    <td @class(['font-semibold text-red-600' => $ticket->priority === 'high'])>{{ __('support.priority_'.$ticket->priority) }}</td>
                    <td>{{ __('support.status_'.$ticket->status) }}</td>
                    <td>{{ $ticket->last_reply_at?->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-4 text-gray-500">{{ __('admin.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $tickets->links() }}</div>
    </x-ui.card>
</x-layouts.admin>
