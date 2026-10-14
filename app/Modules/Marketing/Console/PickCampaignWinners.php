<?php

namespace App\Modules\Marketing\Console;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Services\CampaignService;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Console\Command;

/** When the waiting time of a subject test is over, the subject with more opens goes to everyone else. */
class PickCampaignWinners extends Command
{
    protected $signature = 'marketing:pick-winners';

    protected $description = 'Send the winning subject of finished A/B tests to the rest of the audience';

    public function handle(TenantContext $tenant, CampaignService $campaigns): int
    {
        $picked = 0;

        Restaurant::query()->chunkById(100, function ($restaurants) use ($tenant, $campaigns, &$picked) {
            foreach ($restaurants as $restaurant) {
                if ($restaurant->isSuspended()) {
                    continue;
                }

                $tenant->runAs($restaurant, function () use ($restaurant, $campaigns, &$picked) {
                    foreach ($campaigns->dueForWinner() as $campaign) {
                        $picked += $campaigns->pickWinner($restaurant, $campaign) !== null ? 1 : 0;
                    }
                });
            }
        });

        $this->info("Picked {$picked} winner(s).");

        return self::SUCCESS;
    }
}
