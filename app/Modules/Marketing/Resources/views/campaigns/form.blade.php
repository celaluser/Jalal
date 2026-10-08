@php($editing = $campaign !== null)
<x-layouts.app :title="$editing ? __('marketing.edit_campaign') : __('marketing.new_campaign')">
    <x-ui.page-header :title="$editing ? __('marketing.edit_campaign') : __('marketing.new_campaign')" :back="['url' => route('campaigns.index'), 'label' => __('marketing.campaigns_title')]" />

    <form method="POST" action="{{ $editing ? route('campaigns.update', $campaign->id) : route('campaigns.store') }}" class="card card-pad max-w-2xl space-y-5">
        @csrf @if ($editing) @method('PUT') @endif
        <x-ui.input name="name" :label="__('marketing.campaign_name')" :value="$campaign?->name" :hint="__('marketing.campaign_name_help')" required maxlength="120" />
        <div class="grid gap-4 sm:grid-cols-2" x-data="{ channel: @js(old('channel', $campaign?->channel ?? 'email')) }">
            <div>
                <label for="channel" class="mb-1.5 block text-sm font-medium">{{ __('marketing.campaign_channel') }}</label>
                <select id="channel" name="channel" x-model="channel" class="field">@foreach (['email', 'sms', 'whatsapp'] as $c)<option value="{{ $c }}">{{ __('marketing.channel_'.$c) }}</option>@endforeach</select>
                <p class="mt-1.5 text-xs text-muted" x-show="channel !== 'email'" x-cloak>{{ __('marketing.channel_help') }}</p>
            </div>
            <div>
                <label for="segment" class="mb-1.5 block text-sm font-medium">{{ __('marketing.campaign_segment') }}</label>
                <select id="segment" name="segment" class="field">@foreach (\App\Modules\Marketing\Services\Segments::ALL as $seg)<option value="{{ $seg }}" @selected(old('segment', $campaign?->segment ?? 'all') === $seg)>{{ __('marketing.segment_'.$seg) }}</option>@endforeach</select>
            </div>
            <div class="sm:col-span-2" x-show="channel === 'email'"><x-ui.input name="subject" :label="__('marketing.campaign_subject')" :value="$campaign?->subject" maxlength="160" /></div>
        </div>
        <div>
            <label for="body" class="mb-1.5 block text-sm font-medium">{{ __('marketing.campaign_body') }}</label>
            <textarea id="body" name="body" rows="10" maxlength="10000" required class="field" @error('body') aria-invalid="true" @enderror>{{ old('body', $campaign?->body) }}</textarea>
            @error('body')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@else<p class="mt-1.5 text-xs text-muted">{{ __('marketing.campaign_body_help') }}</p>@enderror
        </div>
        <div>
            <x-ui.input name="min_orders" type="number" min="0" max="1000" :label="__('marketing.min_orders')" :value="$campaign?->min_orders ?? 0" :hint="__('marketing.min_orders_help')" />
            <p class="mt-2 text-sm text-muted">{{ trans_choice('marketing.audience_now', $audience, ['count' => $audience]) }} {{ __('marketing.audience_note') }}</p>
        </div>
        <div class="flex gap-3"><x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button><a href="{{ route('campaigns.index') }}" class="btn btn-secondary">{{ __('admin.cancel') }}</a></div>
    </form>
</x-layouts.app>
