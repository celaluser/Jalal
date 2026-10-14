<x-layouts.app :title="__('support.title')">
    <x-ui.page-header :title="__('support.title')" :description="__('support.description')">
        <x-slot:actions><a href="{{ route('support.create') }}" class="btn btn-primary"><x-ui.icon name="plus" size="4" />{{ __('support.new_ticket') }}</a></x-slot:actions>
    </x-ui.page-header>
    <x-ui.table>
        <thead><tr><th>{{ __('support.subject') }}</th><th>{{ __('admin.status') }}</th><th>{{ __('support.last_activity') }}</th></tr></thead>
        <tbody>
        @forelse ($tickets as $ticket)
            <tr>
                <td><a class="link" href="{{ route('support.show', $ticket) }}">{{ $ticket->subject }}</a></td>
                <td><x-ui.status :value="$ticket->status" :label="__('support.status_'.$ticket->status)" /></td>
                <td class="text-muted">{{ $ticket->last_reply_at?->diffForHumans() }}</td>
            </tr>
        @empty
            <tr class="hover:!bg-transparent"><td colspan="3"><x-ui.empty icon="life-buoy" :title="__('support.empty')" :text="__('support.empty_text')"><a href="{{ route('support.create') }}" class="btn btn-primary">{{ __('support.new_ticket') }}</a></x-ui.empty></td></tr>
        @endforelse
        </tbody>
        <x-slot:footer>{{ $tickets->links() }}</x-slot:footer>
    </x-ui.table>
</x-layouts.app>
