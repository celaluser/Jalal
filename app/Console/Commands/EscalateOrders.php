<?php

namespace App\Console\Commands;

use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Console\Command;

/**
 * Runs every minute. When a restaurant asked for it (Ordering settings: escalate after N minutes), the owner gets an
 * e-mail about each new order that nobody has accepted in time. One e-mail per order.
 */
class EscalateOrders extends Command
{
    protected $signature = 'orders:escalate';

    protected $description = 'E-mail owners about orders nobody accepted in time';

    public function handle(TenantContext $tenant): int
    {
        $sent = 0;

        Restaurant::query()->whereNotNull('order_settings')->chunkById(100, function ($restaurants) use ($tenant, &$sent) {
            foreach ($restaurants as $restaurant) {
                $minutes = (int) (($restaurant->order_settings ?? [])['escalate_minutes'] ?? 0);

                if ($minutes < 1 || $restaurant->isSuspended()) {
                    continue;
                }

                $tenant->runAs($restaurant, function () use ($restaurant, $minutes, &$sent) {
                    $late = Order::where('status', OrderStatus::NEW)->whereNull('escalated_at')->whereNull('scheduled_for')->where('created_at', '<=', now()->subMinutes($minutes))->limit(20)->get();

                    foreach ($late as $order) {
                        $order->forceFill(['escalated_at' => now()])->saveQuietly(); // marked first so a mail failure is not repeated every minute
                        SafeMail::send($restaurant->owner?->email, new TemplatedMail('order_unaccepted', [
                            'restaurant' => $restaurant->name, 'number' => '#'.$order->number, 'minutes' => (string) $order->created_at->diffInMinutes(now()), 'order_url' => url('/orders/'.$order->id),
                        ], $restaurant->locale));
                        $sent++;
                    }
                });
            }
        });

        $this->info("Escalated {$sent} order(s).");

        return self::SUCCESS;
    }
}
