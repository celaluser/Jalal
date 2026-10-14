<?php

namespace App\Modules\Marketing\Jobs;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Models\Campaign;
use App\Modules\Marketing\Services\CampaignService;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Sends one campaign in the background, as its restaurant. */
class SendCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $restaurantId, public readonly int $campaignId) {}

    public function handle(TenantContext $tenant, CampaignService $campaigns): void
    {
        $restaurant = Restaurant::find($this->restaurantId);

        if (! $restaurant) {
            return;
        }

        $tenant->runAs($restaurant, function () use ($restaurant, $campaigns) {
            if ($campaign = Campaign::find($this->campaignId)) {
                $campaigns->deliver($restaurant, $campaign);
            }
        });
    }
}
