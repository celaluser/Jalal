<?php

namespace App\Modules\Reservations\Console;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Reservations\Services\ReservationDeposits;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Console\Command;

/** Every few minutes: bookings whose deposit was not paid in time are cancelled so their tables are free again. */
class ExpireDeposits extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Cancel reservations whose deposit was not paid in time';

    public function handle(TenantContext $tenant, ReservationDeposits $deposits): int
    {
        $total = 0;

        Restaurant::query()->chunkById(100, function ($restaurants) use ($tenant, $deposits, &$total) {
            foreach ($restaurants as $restaurant) {
                $total += $tenant->runAs($restaurant, fn () => $deposits->expire($restaurant));
            }
        });

        $this->info("Released {$total} unpaid booking(s).");

        return self::SUCCESS;
    }
}
