<?php

namespace App\Modules\Store\Console;

use App\Modules\Store\Services\Entitlements;
use Illuminate\Console\Command;

class ExpireStoreItems extends Command
{
    protected $signature = 'store:expire';

    protected $description = 'Mark ended store rentals and trials as expired';

    public function handle(Entitlements $entitlements): int
    {
        $this->info($entitlements->expireDue().' ended.');

        return self::SUCCESS;
    }
}
