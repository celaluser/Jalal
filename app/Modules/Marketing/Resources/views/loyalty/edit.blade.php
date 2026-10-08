<x-layouts.app :title="__('marketing.loyalty_title')">
    <x-ui.page-header :title="__('marketing.loyalty_title')" :description="__('marketing.loyalty_sub')" />

    <form method="POST" action="{{ route('marketing.settings.update') }}" class="max-w-2xl space-y-6" x-data="{ loyalty: @js((bool) old('loyalty_enabled', $s['loyalty_enabled'])), type: @js(old('loyalty_reward_type', $s['loyalty_reward_type'])) }">
        @csrf @method('PUT')

        <x-ui.card :title="__('marketing.loyalty_section')">
            <div class="space-y-5">
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="loyalty_enabled" value="1" x-model="loyalty" class="mt-1 size-4 accent-[var(--color-accent-600)]">
                    <span><span class="block font-medium">{{ __('marketing.loyalty_enabled') }}</span><span class="block text-sm text-muted">{{ __('marketing.loyalty_enabled_help') }}</span></span>
                </label>
                <div class="grid gap-4 sm:grid-cols-2" x-show="loyalty" x-cloak>
                    <x-ui.input name="loyalty_every" type="number" min="2" max="100" :value="$s['loyalty_every']" :label="__('marketing.loyalty_every')" />
                    <x-ui.input name="loyalty_valid_days" type="number" min="1" max="365" :value="$s['loyalty_valid_days']" :label="__('marketing.loyalty_valid')" />
                    <div>
                        <label for="loyalty_reward_type" class="mb-1.5 block text-sm font-medium">{{ __('marketing.loyalty_type') }}</label>
                        <select id="loyalty_reward_type" name="loyalty_reward_type" x-model="type" class="field"><option value="percent">{{ __('marketing.type_percent') }}</option><option value="fixed">{{ __('marketing.type_fixed') }}</option></select>
                    </div>
                    <div>
                        <x-ui.input name="loyalty_reward_value" type="number" step="0.01" min="0.01" :value="$s['loyalty_reward_value']" :label="__('marketing.loyalty_value')" />
                        <p class="mt-1.5 text-xs text-muted"><span x-show="type === 'percent'">{{ __('marketing.loyalty_percent_hint') }}</span><span x-show="type === 'fixed'" x-cloak>{{ __('marketing.loyalty_fixed_hint') }} ({{ $restaurant->currency_code }})</span></p>
                    </div>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('marketing.reviews_section')">
            <div class="space-y-3">
                <x-ui.checkbox name="reviews_enabled" :label="__('marketing.reviews_enabled')" :checked="$s['reviews_enabled']" />
                <x-ui.checkbox name="ai_assistant" :label="__('marketing.ai_assistant')" :checked="$s['ai_assistant']" />
                <p class="-mt-2 text-xs text-muted">{{ __('marketing.ai_assistant_hint') }}</p>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2"><x-ui.input name="review_url" type="url" :label="__('marketing.review_url')" :value="$s['review_url']" :hint="__('marketing.review_url_hint')" maxlength="500" dir="ltr" /></div>
                    <x-ui.input name="review_min" type="number" min="1" max="5" :label="__('marketing.review_min')" :value="$s['review_min']" />
                </div>
                <x-ui.checkbox name="review_request_email" :label="__('marketing.review_email')" :checked="$s['review_request_email']" />
                <x-ui.checkbox name="show_rating" :label="__('marketing.show_rating')" :checked="$s['show_rating']" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('marketing.growth_section')">
            <div class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="tier_silver" type="number" min="2" :value="$s['tier_silver']" :label="__('marketing.tier_silver')" :hint="__('marketing.tier_help')" />
                    <x-ui.input name="tier_gold" type="number" min="3" :value="$s['tier_gold']" :label="__('marketing.tier_gold')" />
                </div>
                <x-ui.checkbox name="weekly_digest" :label="__('marketing.weekly_digest')" :checked="$s['weekly_digest']" />
                <x-ui.checkbox name="nps_enabled" :label="__('marketing.nps_enabled')" :checked="$s['nps_enabled']" />
                <x-ui.checkbox name="autopilot_winback" :label="__('marketing.autopilot_winback')" :checked="$s['autopilot_winback']" />
                <p class="-mt-3 text-xs text-muted">{{ __('marketing.autopilot_help') }}</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="autopilot_days" type="number" min="7" max="365" :value="$s['autopilot_days']" :label="__('marketing.autopilot_days')" />
                    <x-ui.input name="autopilot_percent" type="number" min="1" max="100" :value="$s['autopilot_percent']" :label="__('marketing.autopilot_percent')" />
                </div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('marketing.privacy_section')">
            <x-ui.input name="retention_months" type="number" min="0" max="120" :value="$s['retention_months']" :label="__('marketing.retention_months')" :hint="__('marketing.retention_help')" />
        </x-ui.card>

        <x-ui.card :title="__('marketing.pixels_section')">
            <p class="mb-4 text-sm text-muted">{{ __('marketing.pixels_help') }}</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input name="pixel_meta" :value="$s['pixel_meta']" :label="__('marketing.pixel_meta')" placeholder="1234567890" dir="ltr" />
                <x-ui.input name="pixel_ga" :value="$s['pixel_ga']" :label="__('marketing.pixel_ga')" placeholder="G-XXXXXXXXXX" dir="ltr" />
                <x-ui.input name="pixel_tiktok" :value="$s['pixel_tiktok']" :label="__('marketing.pixel_tiktok')" placeholder="C1ABCDEF2GHI" dir="ltr" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('marketing.links_section')">
            <p class="mb-4 text-sm text-muted">{{ __('marketing.links_help', ['url' => $restaurant->publicUrl('links')]) }}</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="link_phone" :value="$s['link_phone']" :label="__('marketing.link_phone')" dir="ltr" />
                <x-ui.input name="link_whatsapp" :value="$s['link_whatsapp']" :label="__('marketing.link_whatsapp')" :hint="__('marketing.link_whatsapp_hint')" dir="ltr" />
                <x-ui.input name="link_instagram" type="url" :value="$s['link_instagram']" :label="__('marketing.link_instagram')" dir="ltr" />
                <x-ui.input name="link_facebook" type="url" :value="$s['link_facebook']" :label="__('marketing.link_facebook')" dir="ltr" />
                <div class="sm:col-span-2"><x-ui.input name="link_website" type="url" :value="$s['link_website']" :label="__('marketing.link_website')" dir="ltr" /></div>
            </div>
            <p class="mt-4 flex flex-wrap gap-3 text-sm"><a class="font-semibold underline" href="{{ route('marketing.flyer') }}" target="_blank">{{ __('marketing.flyer_open') }}</a><a class="font-semibold underline" href="{{ route('marketing.widget') }}">{{ __('marketing.widget_open') }}</a></p>
        </x-ui.card>

        <x-ui.card :title="__('marketing.messages_section')">
            <x-ui.input name="calling_code" :value="$s['calling_code']" :label="__('marketing.calling_code')" :hint="__('marketing.calling_code_help')" placeholder="90" dir="ltr" />
        </x-ui.card>

        <x-ui.card :title="__('marketing.email_section')">
            <x-ui.input name="campaign_daily_cap" type="number" min="1" :max="$ceiling" :value="$s['campaign_daily_cap']" :label="__('marketing.daily_cap')" :hint="__('marketing.daily_cap_help', ['max' => $ceiling])" />
        </x-ui.card>

        <x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button>
    </form>
</x-layouts.app>
