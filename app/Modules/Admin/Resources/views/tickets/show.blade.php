<x-layouts.admin :title="$ticket->subject">
    <x-ui.page-header :title="$ticket->subject" :back="['url' => route('admin.tickets.index'), 'label' => __('admin.nav.tickets')]">
        <x-slot:actions><x-ui.status :value="$ticket->status" :label="__('support.status_'.$ticket->status)" /></x-slot:actions>
    </x-ui.page-header>
    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            @include('support::thread', ['ticket' => $ticket])
            <form method="POST" action="{{ route('admin.tickets.reply', $ticket->id) }}">
                @csrf
                <x-ui.card class="space-y-3">
                    <label for="message" class="block text-sm font-medium">{{ __('support.your_reply') }}</label>
                    <textarea id="message" name="message" rows="5" required class="field">{{ old('message') }}</textarea>
                    @error('message')<p class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                    <div class="flex gap-2">
                        <x-ui.button :block="false" icon="arrow-right">{{ __('support.send') }}</x-ui.button>
                        @if ($ticket->isClosed())
                            <x-ui.button variant="secondary" :block="false" formaction="{{ route('admin.tickets.reopen', $ticket->id) }}" formnovalidate>{{ __('admin.tickets.reopen') }}</x-ui.button>
                        @else
                            <x-ui.button variant="secondary" :block="false" formaction="{{ route('admin.tickets.close', $ticket->id) }}" formnovalidate>{{ __('support.close') }}</x-ui.button>
                        @endif
                    </div>
                </x-ui.card>
            </form>
        </div>
        <x-ui.card :title="__('admin.tickets.details')">
            <dl class="space-y-3 text-sm">
                <div><dt class="eyebrow">{{ __('admin.restaurants.name') }}</dt><dd class="mt-0.5">@if ($restaurant)<a class="link" href="{{ route('admin.restaurants.show', $restaurant->id) }}">{{ $restaurant->name }}</a>@endif</dd></div>
                <div><dt class="eyebrow">{{ __('admin.tickets.opened_by') }}</dt><dd class="mt-0.5">{{ $ticket->user?->name }}<span class="block text-xs text-muted">{{ $ticket->user?->email }}</span></dd></div>
                <div><dt class="eyebrow">{{ __('support.priority') }}</dt><dd class="mt-0.5">{{ __('support.priority_'.$ticket->priority) }}</dd></div>
                <div><dt class="eyebrow">{{ __('admin.tickets.opened') }}</dt><dd class="mt-0.5">{{ $ticket->created_at->toDayDateTimeString() }}</dd></div>
            </dl>
        </x-ui.card>
    </div>
</x-layouts.admin>
