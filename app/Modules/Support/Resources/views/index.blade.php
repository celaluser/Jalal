<x-layouts.app :title="__('support.title')">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('support.title') }}</h1>
        <a href="{{ route('support.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('support.new_ticket') }}</a>
    </div>
    @if (session('status'))<x-ui.alert>{{ session('status') }}</x-ui.alert>@endif
    <x-ui.card class="overflow-x-auto">
        <table class="w-full min-w-[480px] text-sm">
            <thead class="text-gray-500"><tr><th class="py-2 text-start">{{ __('support.subject') }}</th><th class="text-start">{{ __('admin.status') }}</th><th class="text-start">{{ __('support.last_activity') }}</th></tr></thead>
            <tbody>
            @forelse ($tickets as $ticket)
                <tr class="border-t border-gray-100 dark:border-gray-800">
                    <td class="py-2"><a class="font-medium text-brand-600 hover:underline" href="{{ route('support.show', $ticket) }}">{{ $ticket->subject }}</a></td>
                    <td>{{ __('support.status_'.$ticket->status) }}</td>
                    <td>{{ $ticket->last_reply_at?->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-4 text-gray-500">{{ __('support.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $tickets->links() }}</div>
    </x-ui.card>
</x-layouts.app>
