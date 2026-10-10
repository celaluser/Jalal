<?php

namespace App\Console\Commands;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Marketing\Services\CustomerService;
use App\Modules\Marketing\Services\MarketingSettings;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Console\Command;

/**
 * Data retention: for restaurants that set a retention period, guests who have been quiet for that long are erased,
 * and personal details are removed from finished orders older than the period (the amounts and dishes stay for accounting).
 */
class PrunePersonalData extends Command
{
    protected $signature = 'privacy:prune';

    protected $description = 'Erase guest personal data older than each restaurant\'s retention period';

    public function handle(TenantContext $tenant, MarketingSettings $settings, CustomerService $customers): int
    {
        $erased = 0;
        $orders = 0;

        Restaurant::query()->chunkById(100, function ($restaurants) use ($tenant, $settings, $customers, &$erased, &$orders) {
            foreach ($restaurants as $restaurant) {
                $months = (int) $settings->get($restaurant, 'retention_months');

                if ($months < 1) {
                    continue;
                }

                $tenant->runAs($restaurant, function () use ($months, $customers, &$erased, &$orders) {
                    $cutoff = now()->subMonths($months);

                    Customer::where(fn ($q) => $q->where('last_order_at', '<', $cutoff)->orWhere(fn ($q) => $q->whereNull('last_order_at')->where('created_at', '<', $cutoff)))
                        ->each(function (Customer $c) use ($customers, &$erased) {
                            $customers->forget($c);
                            $erased++;
                        });

                    $orders += Order::whereIn('status', OrderStatus::FINAL)->where('created_at', '<', $cutoff)
                        ->where(fn ($q) => $q->whereNotNull('customer_name')->orWhereNotNull('customer_phone')->orWhereNotNull('customer_email')->orWhereNotNull('delivery_address'))
                        ->update(['customer_name' => null, 'customer_phone' => null, 'customer_email' => null, 'delivery_address' => null]);
                });
            }
        });

        $this->info("Erased {$erased} guest(s) and cleaned {$orders} order(s).");

        return self::SUCCESS;
    }
}
