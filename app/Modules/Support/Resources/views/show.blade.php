<x-layouts.app :title="$ticket->subject">
    <x-ui.page-header :title="$ticket->subject" :back="['url' => route('support.index'), 'label' => __('support.title')]">
        <x-slot:actions><x-ui.status :value="$ticket->status" :label="__('support.status_'.$ticket->status)" /></x-slot:actions>
    </x-ui.page-header>
    <div class="max-w-3xl space-y-5">
        @include('support::thread', ['ticket' => $ticket])
        <form method="POST" action="{{ route('support.reply', $ticket) }}">
            @csrf
            <x-ui.card class="space-y-3">
                <label for="message" class="block text-sm font-medium">{{ $ticket->isClosed() ? __('support.reopen_by_replying') : __('support.your_reply') }}</label>
                <textarea id="message" name="message" rows="5" required class="field">{{ old('message') }}</textarea>
                @error('message')<p class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                <div class="flex flex-wrap gap-2">
                    <x-ui.button :block="false" icon="arrow-right">{{ __('support.send') }}</x-ui.button>
                    @unless ($ticket->isClosed())<x-ui.button variant="secondary" :block="false" formaction="{{ route('support.close', $ticket) }}" formnovalidate>{{ __('support.close') }}</x-ui.button>@endunless
                </div>
            </x-ui.card>
        </form>
    </div>
</x-layouts.app>
