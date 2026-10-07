<x-layouts.app :title="$ticket->subject">
    <div class="mx-auto max-w-3xl space-y-4">
        <a href="{{ route('support.index') }}" class="text-sm text-brand-600 hover:underline">← {{ __('support.title') }}</a>
        @if (session('status'))<x-ui.alert>{{ session('status') }}</x-ui.alert>@endif
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ $ticket->subject }}</h1>
            <span class="rounded-full bg-gray-200 px-3 py-1 text-xs dark:bg-gray-800">{{ __('support.status_'.$ticket->status) }}</span>
        </div>
        @include('support::thread', ['ticket' => $ticket])
        @unless ($ticket->isClosed())
            <form method="POST" action="{{ route('support.close', $ticket) }}" class="text-end">@csrf<button class="text-sm text-gray-500 hover:underline">{{ __('support.close') }}</button></form>
        @endunless
        <form method="POST" action="{{ route('support.reply', $ticket) }}">
            @csrf
            <x-ui.card class="space-y-3">
                <label for="message" class="block text-sm font-medium">{{ $ticket->isClosed() ? __('support.reopen_by_replying') : __('support.your_reply') }}</label>
                <textarea id="message" name="message" rows="5" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950">{{ old('message') }}</textarea>
                @error('message')<p class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                <x-ui.button class="!w-auto">{{ __('support.send') }}</x-ui.button>
            </x-ui.card>
        </form>
    </div>
</x-layouts.app>
