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

        <x-ui.card :title="__('marketing.messages_section')">
            <x-ui.input name="calling_code" :value="$s['calling_code']" :label="__('marketing.calling_code')" :hint="__('marketing.calling_code_help')" placeholder="90" dir="ltr" />
        </x-ui.card>

        <x-ui.card :title="__('marketing.email_section')">
            <x-ui.input name="campaign_daily_cap" type="number" min="1" :max="$ceiling" :value="$s['campaign_daily_cap']" :label="__('marketing.daily_cap')" :hint="__('marketing.daily_cap_help', ['max' => $ceiling])" />
        </x-ui.card>

        <x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button>
    </form>
</x-layouts.app>
