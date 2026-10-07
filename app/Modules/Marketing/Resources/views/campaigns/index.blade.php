<x-layouts.app :title="__('marketing.campaigns_title')">
    <x-ui.page-header :title="__('marketing.campaigns_title')" :description="__('marketing.campaigns_sub')">
        <x-slot:actions><a href="{{ route('campaigns.create') }}" class="btn btn-primary"><x-ui.icon name="plus" size="4" />{{ __('marketing.new_campaign') }}</a></x-slot:actions>
    </x-ui.page-header>

    <p class="mb-4 text-sm text-muted">{{ trans_choice('marketing.audience_now', $audience, ['count' => $audience]) }}</p>

    @if ($campaigns->isEmpty())
        <div class="card"><x-ui.empty icon="mail" :title="__('marketing.campaigns_empty')" :text="__('marketing.campaigns_empty_text')" /></div>
    @else
        <ul class="grid gap-3">
            @foreach ($campaigns as $c)
                <li><a href="{{ route('campaigns.show', $c->id) }}" class="card flex flex-wrap items-center gap-4 p-4 transition hover:shadow-md">
                    <div class="grid size-12 shrink-0 place-items-center rounded-xl bg-surface-2 text-muted"><x-ui.icon name="mail" size="5" /></div>
                    <div class="min-w-0 flex-1"><p class="truncate font-semibold">{{ $c->name }}</p><p class="truncate text-sm text-muted">{{ $c->subject }}</p></div>
                    @if ($c->status === 'sent')<p class="tnum text-sm text-muted"><bdi>{{ $c->sent_count }}</bdi> {{ __('marketing.sent_count') }} · {{ $c->sent_at?->diffForHumans() }}</p>@endif
                    <x-ui.badge :tone="['draft' => 'neutral', 'sending' => 'warning', 'sent' => 'success'][$c->status]" dot>{{ __('marketing.status_'.$c->status) }}</x-ui.badge>
                </a></li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $campaigns->links() }}</div>
    @endif
</x-layouts.app>
