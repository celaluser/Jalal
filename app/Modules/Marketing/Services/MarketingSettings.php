<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Activity\Services\ActivityLogger;
use App\Modules\Tenancy\Models\Restaurant;

/** The restaurant's marketing options: loyalty reward, reviews and the campaign sending cap. */
class MarketingSettings
{
    public const DEFAULTS = [
        'loyalty_enabled' => false,
        'loyalty_every' => 5,            // a reward after every N completed orders
        'loyalty_reward_type' => 'percent',
        'loyalty_reward_value' => '10',  // percent, or an amount in the restaurant currency
        'loyalty_valid_days' => 60,
        'reviews_enabled' => true,
        'review_request_email' => true,
        'review_url' => '',               // where happy guests are sent to review the restaurant publicly (Google, Tripadvisor...)
        'review_min' => 4,               // stars from which that link is offered
        'ai_assistant' => false,         // a chat on the guest menu that answers questions about the dishes
        'show_rating' => true,           // average rating on the public menu
        'campaign_daily_cap' => 200,
        'calling_code' => '',            // country code for local phone numbers, e.g. 90
        'tier_silver' => 5,              // orders to reach the silver / gold tier
        'tier_gold' => 15,
        'autopilot_winback' => false,    // automatic "we miss you" message with a personal code
        'autopilot_days' => 45,          // quiet for this many days = lapsed
        'autopilot_percent' => 15,
        'retention_months' => 0,         // 0 = keep guest data; otherwise personal data of guests quiet for this long is erased
        'weekly_digest' => true,         // Monday e-mail with last week's numbers, to the owner
        'nps_enabled' => true,           // the 0-10 "would you recommend us" question next to the stars
        'pixel_meta' => '',              // tracking ids; loaded on the guest menu unless the guest sends Do Not Track
        'pixel_ga' => '',
        'pixel_tiktok' => '',
        'link_phone' => '', 'link_whatsapp' => '', 'link_instagram' => '', 'link_facebook' => '', 'link_website' => '', // the link-in-bio page
    ];

    /** @return array<string, mixed> */
    public function for(Restaurant $restaurant): array
    {
        $saved = is_array($restaurant->marketing_settings) ? $restaurant->marketing_settings : [];

        return array_replace(self::DEFAULTS, array_intersect_key($saved, self::DEFAULTS));
    }

    public function get(Restaurant $restaurant, string $key): mixed
    {
        return $this->for($restaurant)[$key];
    }

    /** What this restaurant may send today at most: its own limit, never above the platform ceiling. */
    public function dailyCap(Restaurant $restaurant): int
    {
        return max(0, min((int) $this->get($restaurant, 'campaign_daily_cap'), (int) config('marketing.daily_email_cap')));
    }

    /** @param array<string, mixed> $input validated by rules() */
    public function save(Restaurant $restaurant, array $input): void
    {
        $clean = [];

        foreach (self::DEFAULTS as $key => $default) {
            $clean[$key] = match (true) {
                is_bool($default) => ! empty($input[$key]),
                is_int($default) => (int) ($input[$key] ?? $default),
                default => trim((string) ($input[$key] ?? $default)),
            };
        }

        $restaurant->update(['marketing_settings' => $clean]);
        app(ActivityLogger::class)->record('updated', $restaurant, [], 'Marketing settings');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'loyalty_enabled' => ['nullable', 'boolean'],
            'loyalty_every' => ['required', 'integer', 'min:2', 'max:100'],
            'loyalty_reward_type' => ['required', 'in:percent,fixed'],
            'loyalty_reward_value' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'loyalty_valid_days' => ['required', 'integer', 'min:1', 'max:365'],
            'reviews_enabled' => ['nullable', 'boolean'], 'ai_assistant' => ['nullable', 'boolean'], 'review_request_email' => ['nullable', 'boolean'], 'show_rating' => ['nullable', 'boolean'],
            'review_url' => ['nullable', 'url:https', 'max:500'], 'review_min' => ['nullable', 'integer', 'between:1,5'],
            'calling_code' => ['nullable', 'regex:/^\+?\d{1,4}$/'],
            'tier_silver' => ['sometimes', 'required', 'integer', 'min:2', 'max:500'], 'tier_gold' => ['sometimes', 'required', 'integer', 'gt:tier_silver', 'max:1000'],
            'autopilot_winback' => ['nullable', 'boolean'], 'autopilot_days' => ['sometimes', 'required', 'integer', 'min:7', 'max:365'], 'autopilot_percent' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'nps_enabled' => ['nullable', 'boolean'], 'weekly_digest' => ['nullable', 'boolean'], 'retention_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'pixel_meta' => ['nullable', 'regex:/^\d{5,20}$/'], 'pixel_ga' => ['nullable', 'regex:/^(G|GT|AW)-[A-Z0-9]{4,15}$/'], 'pixel_tiktok' => ['nullable', 'regex:/^[A-Z0-9]{8,30}$/'],
            'link_phone' => ['nullable', 'regex:/^\+?[\d\s()-]{6,25}$/'], 'link_whatsapp' => ['nullable', 'regex:/^\+?\d{7,15}$/'],
            'link_instagram' => ['nullable', 'url:https', 'max:300'], 'link_facebook' => ['nullable', 'url:https', 'max:300'], 'link_website' => ['nullable', 'url:https', 'max:300'],
            'campaign_daily_cap' => ['required', 'integer', 'min:1', 'max:'.max(1, (int) config('marketing.daily_email_cap'))],
        ];
    }
}
