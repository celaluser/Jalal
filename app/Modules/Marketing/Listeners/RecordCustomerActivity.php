<?php

namespace App\Modules\Marketing\Listeners;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Marketing\Services\CustomerService;
use App\Modules\Marketing\Services\LoyaltyService;
use App\Modules\Marketing\Services\ReviewService;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Events\Dispatcher;
use Throwable;

/**
 * Turns order events into CRM facts: the customer record, loyalty rewards and review requests.
 * Never lets a marketing problem touch the order itself.
 */
class RecordCustomerActivity
{
    public function __construct(
        private readonly CustomerService $customers,
        private readonly LoyaltyService $loyalty,
        private readonly ReviewService $reviews,
        private readonly TenantContext $tenant,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(OrderPlaced::class, fn (OrderPlaced $e) => $this->safely($e->order->restaurant_id, fn () => $this->customers->recordOrder($e->order)));

        $events->listen(OrderStatusChanged::class, fn (OrderStatusChanged $e) => $this->safely($e->order->restaurant_id, function (Restaurant $restaurant) use ($e) {
            if (! in_array($e->to, [OrderStatus::COMPLETED, OrderStatus::CANCELLED], true)) {
                return;
            }

            if ($customer = $e->order->customer_id ? Customer::find($e->order->customer_id) : null) {
                $this->customers->refresh($customer);
            }

            if ($e->to === OrderStatus::COMPLETED) {
                $this->loyalty->onCompleted($restaurant, $e->order);
                $this->reviews->requestByEmail($restaurant, $e->order);
            }
        }));
    }

    private function safely(int $restaurantId, callable $work): void
    {
        try {
            $restaurant = Restaurant::find($restaurantId);

            if ($restaurant) {
                $this->tenant->runAs($restaurant, fn () => $work($restaurant));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
