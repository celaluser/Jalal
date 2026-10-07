<x-layouts.admin :title="$ticket->subject">
    <div class="mx-auto max-w-3xl space-y-4">
        <a href="{{ route('admin.tickets.index') }}" class="text-sm text-brand-600 hover:underline">← {{ __('admin.nav.tickets') }}</a>
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">{{ $ticket->subject }}</h1>
                <p class="text-sm text-gray-500">
                    @if ($restaurant)<a class="text-brand-600 hover:underline" href="{{ route('admin.restaurants.show', $restaurant->id) }}">{{ $restaurant->name }}</a>@endif
                    · {{ $ticket->user?->name }} · {{ __('support.priority_'.$ticket->priority) }}
                </p>
            </div>
            <span class="rounded-full bg-gray-200 px-3 py-1 text-xs dark:bg-gray-800">{{ __('support.status_'.$ticket->status) }}</span>
        </div>
        @include('support::thread', ['ticket' => $ticket])
        <form method="POST" action="{{ route('admin.tickets.reply', $ticket->id) }}">
            @csrf
            <x-ui.card class="space-y-3">
                <label for="message" class="block text-sm font-medium">{{ __('support.your_reply') }}</label>
                <textarea id="message" name="message" rows="5" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950">{{ old('message') }}</textarea>
                @error('message')<p class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                <div class="flex gap-2">
                    <x-ui.button class="!w-auto">{{ __('support.send') }}</x-ui.button>
                    @if ($ticket->isClosed())
                        <x-ui.button variant="secondary" class="!w-auto" formaction="{{ route('admin.tickets.reopen', $ticket->id) }}" formnovalidate>{{ __('admin.tickets.reopen') }}</x-ui.button>
                    @else
                        <x-ui.button variant="secondary" class="!w-auto" formaction="{{ route('admin.tickets.close', $ticket->id) }}" formnovalidate>{{ __('support.close') }}</x-ui.button>
                    @endif
                </div>
            </x-ui.card>
        </form>
    </div>
</x-layouts.admin>
