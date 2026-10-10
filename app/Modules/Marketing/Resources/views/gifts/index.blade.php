<x-layouts.app :title="__('marketing.gifts_title')">
    <x-ui.page-header :title="__('marketing.gifts_title')" :description="__('marketing.gifts_sub')" />

    <form method="POST" action="{{ route('gifts.store') }}" class="card card-pad mb-6 max-w-3xl space-y-5">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="amount" type="number" step="0.01" min="1" :label="__('marketing.gift_amount').' ('.$restaurant->currency_code.')'" required />
            <x-ui.input name="valid_days" type="number" min="1" max="3650" :label="__('marketing.gift_valid_days')" :hint="__('marketing.gift_valid_help')" />
            <x-ui.input name="recipient_email" type="email" :label="__('marketing.gift_email')" :hint="__('marketing.gift_email_help')" />
            <x-ui.input name="note" :label="__('marketing.gift_note')" maxlength="200" />
        </div>
        <x-ui.button :block="false">{{ __('marketing.gift_issue') }}</x-ui.button>
    </form>

    @if ($cards->isEmpty())
        <div class="card"><x-ui.empty icon="gift" :title="__('marketing.gifts_empty')" :text="__('marketing.gifts_empty_text')" /></div>
    @else
        <ul class="grid gap-3">
            @foreach ($cards as $c)
                <li class="card flex flex-wrap items-center gap-4 p-4">
                    <span class="font-mono text-base font-bold tracking-wide" dir="ltr">{{ $c->code }}</span>
                    <div class="min-w-0 flex-1 text-sm text-muted">{{ __('marketing.gift_balance', ['balance' => $restaurant->money($c->balance_cents / 100), 'initial' => $restaurant->money($c->initial_cents / 100)]) }}@if ($c->expires_at) · {{ __('marketing.gift_expires', ['date' => $c->expires_at->toFormattedDateString()]) }}@endif @if ($c->recipient_email) · {{ $c->recipient_email }}@endif</div>
                    <x-ui.badge :tone="$c->usable() ? 'success' : 'neutral'" dot>{{ $c->usable() ? __('marketing.status_active') : __('marketing.status_off') }}</x-ui.badge>
                    <form method="POST" action="{{ route('gifts.toggle', $c->id) }}">@csrf<button class="btn btn-ghost btn-sm">{{ $c->is_active ? __('marketing.turn_off') : __('marketing.turn_on') }}</button></form>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $cards->links() }}</div>
    @endif
</x-layouts.app>
