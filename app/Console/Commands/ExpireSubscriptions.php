<?php

namespace App\Console\Commands;

use App\Modules\Billing\Services\SubscriptionService;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'billing:expire';

    protected $description = 'Expire finished trials and subscriptions past their grace period';

    public function handle(SubscriptionService $subscriptions): int
    {
        $this->info($subscriptions->expireDue().' subscription(s) updated.');

        return self::SUCCESS;
    }
}
