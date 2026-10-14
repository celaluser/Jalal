<x-layouts.app :title="__('api.title')">
    <x-ui.page-header :title="__('api.title')" :description="__('api.sub')" />

    @unless ($enabled)
        <x-ui.alert type="warning">{{ __('api.not_included') }} <a class="font-semibold underline" href="{{ route('billing.index') }}">{{ __('api.upgrade') }}</a></x-ui.alert>
    @else
        @if (session('new_token'))<x-ui.alert type="success" class="mb-5"><p class="font-medium">{{ __('api.new_token') }}</p><code class="mt-2 block break-all rounded bg-surface-2 p-2 text-sm" dir="ltr">{{ session('new_token') }}</code></x-ui.alert>@endif
        @if (session('new_secret'))<x-ui.alert type="success" class="mb-5"><p class="font-medium">{{ __('api.new_secret', ['url' => session('new_secret')['url']]) }}</p><code class="mt-2 block break-all rounded bg-surface-2 p-2 text-sm" dir="ltr">{{ session('new_secret')['secret'] }}</code></x-ui.alert>@endif

        <div class="grid gap-6 xl:grid-cols-2">
            <x-ui.card :title="__('api.tokens')" :description="__('api.tokens_help')">
                <form method="POST" action="{{ route('integrations.tokens.store') }}" class="mb-5 space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2"><x-ui.input name="name" :label="__('api.token_name')" required maxlength="80" /><x-ui.input name="days" type="number" min="1" max="3650" :label="__('api.token_days')" :hint="__('api.token_days_help')" /></div>
                    <fieldset class="space-y-2">@foreach ($abilities as $a)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="abilities[]" value="{{ $a }}" @checked(in_array($a, old('abilities', ['menu:read', 'orders:read']))) class="size-4 accent-[var(--color-accent-600)]">{{ __('api.ability_'.$a) }} <code class="text-xs text-muted" dir="ltr">{{ $a }}</code></label>@endforeach</fieldset>
                    @error('abilities')<p class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                    <x-ui.button :block="false">{{ __('api.token_create') }}</x-ui.button>
                </form>
                <ul class="divide-y divide-line">@foreach ($tokens as $t)
                    <li class="flex flex-wrap items-center gap-3 py-3"><div class="min-w-0 flex-1"><p class="font-medium">{{ $t->name }} <code class="text-xs text-muted" dir="ltr">{{ $t->prefix }}…</code></p>
                        <p class="text-xs text-muted">{{ collect($t->abilities)->map(fn ($a) => __('api.ability_'.$a))->implode(', ') }} · {{ $t->last_used_at ? __('api.last_used', ['time' => $t->last_used_at->diffForHumans()]) : __('api.never_used') }}@if ($t->expires_at) · {{ __('api.expires', ['date' => $t->expires_at->toFormattedDateString()]) }}@endif</p></div>
                        <form method="POST" action="{{ route('integrations.tokens.destroy', $t->id) }}" onsubmit="return confirm('{{ __('api.revoke_confirm') }}')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">{{ __('api.revoke') }}</button></form></li>
                @endforeach</ul>
            </x-ui.card>

            <x-ui.card :title="__('api.webhooks')" :description="__('api.webhooks_help')">
                <form method="POST" action="{{ route('integrations.webhooks.store') }}" class="mb-5 space-y-4">
                    @csrf
                    <x-ui.input name="url" type="url" :label="__('api.webhook_url')" placeholder="https://example.com/hooks/orders" required dir="ltr" maxlength="500" />
                    <fieldset class="space-y-2"><legend class="mb-1 text-sm font-medium">{{ __('api.webhook_events') }}</legend>@foreach ([...$events, '*'] as $e)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="events[]" value="{{ $e }}" @checked(in_array($e, old('events', ['order.created']))) class="size-4 accent-[var(--color-accent-600)]">{{ __('api.event_'.$e) }}</label>@endforeach</fieldset>
                    @error('events')<p class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                    <x-ui.button :block="false">{{ __('api.webhook_add') }}</x-ui.button>
                </form>
                <ul class="divide-y divide-line">@foreach ($endpoints as $w)
                    <li class="py-3"><div class="flex flex-wrap items-center gap-2"><code class="min-w-0 flex-1 break-all text-sm" dir="ltr">{{ $w->url }}</code>
                        <x-ui.badge :tone="$w->is_active ? 'success' : 'danger'" dot>{{ $w->is_active ? __('api.turn_on') : __('api.turn_off') }}</x-ui.badge></div>
                        @if (! $w->is_active && $w->failures >= \App\Modules\Api\Models\WebhookEndpoint::MAX_FAILURES)<p class="mt-1 text-xs text-red-600">{{ __('api.disabled_after_failures') }}</p>@endif
                        <p class="mt-1 text-xs text-muted">{{ collect($w->events)->map(fn ($e) => __('api.event_'.$e))->implode(', ') }}</p>
                        <div class="mt-2 flex flex-wrap gap-1"><form method="POST" action="{{ route('integrations.webhooks.test', $w->id) }}">@csrf<button class="btn btn-secondary btn-sm">{{ __('api.send_test') }}</button></form>
                            <form method="POST" action="{{ route('integrations.webhooks.toggle', $w->id) }}">@csrf<button class="btn btn-ghost btn-sm">{{ $w->is_active ? __('api.turn_off') : __('api.turn_on') }}</button></form>
                            <form method="POST" action="{{ route('integrations.webhooks.destroy', $w->id) }}" onsubmit="return confirm('{{ __('api.delete_confirm') }}')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" aria-label="{{ __('admin.delete') }}"><x-ui.icon name="trash" size="4" /></button></form></div>
                        @forelse ($w->deliveries as $d)<p class="mt-1 flex gap-2 text-xs text-muted"><span dir="ltr">{{ $d->event }}</span><span>{{ __('api.status_'.$d->status) }}@if ($d->response_code) · {{ $d->response_code }}@endif</span><span>{{ $d->created_at->diffForHumans() }}</span></p>@empty<p class="mt-1 text-xs text-muted">{{ __('api.deliveries_empty') }}</p>@endforelse
                    </li>
                @endforeach</ul>
            </x-ui.card>
        </div>
    @endunless
</x-layouts.app>
