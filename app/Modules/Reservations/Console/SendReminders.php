<?php

namespace App\Modules\Reservations\Console;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Reservations\Models\Reservation;
use App\Modules\Reservations\Services\ReservationService;
use App\Modules\Reservations\Services\ReservationSettings;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Console\Command;

/** Runs every few minutes: e-mails guests whose confirmed booking starts within the restaurant's reminder window. Once per booking. */
class SendReminders extends Command
{
    protected $signature = 'reservations:remind';

    protected $description = 'Remind guests of their upcoming reservations';

    public function handle(TenantContext $tenant, ReservationSettings $settings, ReservationService $service): int
    {
        $sent = 0;

        Restaurant::query()->chunkById(100, function ($restaurants) use ($tenant, $settings, $service, &$sent) {
            foreach ($restaurants as $restaurant) {
                $hours = (int) $settings->for($restaurant)['remind_hours'];

                if ($hours < 1 || ! $settings->active($restaurant) || $restaurant->isSuspended()) {
                    continue;
                }

                $tenant->runAs($restaurant, function () use ($restaurant, $hours, $service, &$sent) {
                    foreach (Reservation::where('status', 'confirmed')->whereNull('reminded_at')->where(fn ($q) => $q->whereNotNull('email')->orWhereNotNull('phone'))->where('starts_at', '>', now())->where('starts_at', '<=', now()->addHours($hours))->limit(100)->get() as $res) {
                        $res->forceFill(['reminded_at' => now()])->save();
                        $service->mail($restaurant, $res, 'reservation_reminder');
                        $service->sms($restaurant, $res);
                        $sent++;
                    }
                });
            }
        });

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
