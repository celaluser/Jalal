<x-layouts.app :title="$campaign->name">
    <x-ui.page-header :title="$campaign->name" :description="$campaign->subject" :back="['url' => route('campaigns.index'), 'label' => __('marketing.campaigns_title')]">
        <x-slot:actions>
            <x-ui.badge :tone="['draft' => 'neutral', 'sending' => 'warning', 'sent' => 'success'][$campaign->status]" dot>{{ __('marketing.status_'.$campaign->status) }}</x-ui.badge>
            @if ($campaign->status === 'draft')<a href="{{ route('campaigns.edit', $campaign->id) }}" class="btn btn-secondary"><x-ui.icon name="pen" size="4" />{{ __('admin.edit') }}</a>@endif
        </x-slot:actions>
    </x-ui.page-header>

    @error('campaign')<x-ui.alert type="error" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    <div class="grid items-start gap-5 lg:grid-cols-3">
        <x-ui.card :title="__('marketing.preview')" class="lg:col-span-2">
            <div class="prose-sm max-w-none space-y-3 [&_a]:underline [&_p]:leading-relaxed">{!! \Illuminate\Support\Str::markdown($campaign->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
        </x-ui.card>

        <div class="space-y-5">
            @if ($campaign->status === 'draft')
                <x-ui.card :title="__('marketing.campaign_audience')">
                    <p class="text-sm">{{ trans_choice('marketing.audience_now', $audience, ['count' => $audience]) }}</p>
                    <p class="mt-1 text-xs text-muted">{{ __('marketing.audience_note') }}</p>
                    <p class="mt-3 text-xs text-muted">{{ __('marketing.today_room', ['count' => $remaining, 'cap' => $cap]) }} {{ __('marketing.cap_note') }}</p>
                    <div class="mt-4 grid gap-2">
                        <form method="POST" action="{{ route('campaigns.send', $campaign->id) }}" onsubmit="return confirm('{{ __('marketing.send_confirm') }}')">@csrf<x-ui.button icon="send" :disabled="$audience === 0">{{ __('marketing.send_now') }}</x-ui.button></form>
                        <form method="POST" action="{{ route('campaigns.test', $campaign->id) }}">@csrf<x-ui.button variant="secondary">{{ __('marketing.send_test') }}</x-ui.button></form>
                        <form method="POST" action="{{ route('campaigns.destroy', $campaign->id) }}" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf @method('DELETE')<button class="btn btn-ghost w-full text-red-600">{{ __('admin.delete') }}</button></form>
                    </div>
                </x-ui.card>
            @else
                <x-ui.card :title="__('marketing.recipients')">
                    <dl class="grid grid-cols-3 gap-3 text-center">
                        <div><dt class="text-xs text-muted">{{ __('marketing.recipients') }}</dt><dd class="display tnum text-2xl font-bold">{{ $campaign->recipients_count }}</dd></div>
                        <div><dt class="text-xs text-muted">{{ __('marketing.sent_count') }}</dt><dd class="display tnum text-2xl font-bold text-accent-700 dark:text-accent-300">{{ $campaign->sent_count }}</dd></div>
                        <div><dt class="text-xs text-muted">{{ __('marketing.skipped_count') }}</dt><dd class="display tnum text-2xl font-bold">{{ $campaign->skipped_count }}</dd></div>
                    </dl>
                    @if ($campaign->sent_at)<p class="mt-4 text-xs text-muted">{{ __('marketing.sent_on', ['date' => $campaign->sent_at->toDayDateTimeString()]) }}</p>@endif
                    @if ($campaign->skipped_count)<p class="mt-2 text-xs text-muted">{{ __('marketing.cap_note') }}</p>@endif
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.app>
