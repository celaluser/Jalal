<?php

namespace App\Console\Commands;

use App\Modules\Billing\Services\SavedCards;
use Illuminate\Console\Command;

/** Charges the kept card of restaurants that chose automatic renewal, a day before their plan ends. */
class AutoRenewSubscriptions extends Command
{
    protected $signature = 'billing:auto-renew';

    protected $description = 'Renew plans automatically with the saved card (restaurants that chose it)';

    public function handle(SavedCards $cards): int
    {
        $done = $cards->renewDue();
        $this->info("Renewed {$done['renewed']}, failed {$done['failed']}.");

        return self::SUCCESS;
    }
}
