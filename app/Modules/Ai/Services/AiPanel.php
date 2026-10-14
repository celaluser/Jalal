<?php

namespace App\Modules\Ai\Services;

use App\Modules\Tenancy\Models\Restaurant;

/** What the AI buttons of a menu form need to know: is AI on, where to call, how many credits are left. */
class AiPanel
{
    public function __construct(private readonly AiManager $manager, private readonly AiCredits $credits) {}

    /** @return array<string, mixed> */
    public function for(Restaurant $restaurant): array
    {
        $summary = $this->credits->summary($restaurant);

        return [
            'enabled' => $this->manager->configured(),
            'locale' => $restaurant->locale,
            'locales' => $restaurant->menuLocales(),
            'credits' => $summary,
            'costs' => collect(['description', 'translation', 'allergens'])->mapWithKeys(fn ($t) => [$t => $this->credits->cost($t)])->all(),
            'urls' => ['describe' => route('ai.describe'), 'translate' => route('ai.translate'), 'tags' => route('ai.tags')],
            'csrf' => csrf_token(),
            'unlimitedText' => __('ai.credits_unlimited'),
            'leftText' => __('ai.credits_left', ['count' => ':count']),
            'failText' => __('ai.error_provider_error'),
            'needName' => __('ai.need_name'),
            'tickedText' => __('ai.tags_applied', ['list' => ':list']),
            'noneText' => __('ai.tags_none'),
            'creditsText' => $summary['remaining'] === null ? __('ai.credits_unlimited') : __('ai.credits_left', ['count' => $summary['remaining']]),
        ];
    }
}
