<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Models\OrderPayment;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Collection;

/**
 * The platform's commission on a restaurant's online payments. It is not collected from the guest: it is added to the
 * restaurant's next subscription invoice, in the invoice's currency. Commission in another currency stays unbilled
 * (the platform admin sees it on the payments screen) rather than being converted at a guessed rate.
 */
class CommissionLedger
{
    /** Unbilled commission, in cents of $currency. */
    public function dueCents(Restaurant $restaurant, string $currency): int
    {
        return (int) $this->unbilled($restaurant, $currency)->sum('commission_cents');
    }

    /** Called once the invoice that carries the commission exists. */
    public function markBilled(Restaurant $restaurant, string $currency): void
    {
        OrderPayment::withoutGlobalScopes()->whereIn('id', $this->unbilled($restaurant, $currency)->pluck('id'))->update(['commission_billed_at' => now()]);
    }

    /** @return Collection<int, OrderPayment> */
    private function unbilled(Restaurant $restaurant, string $currency)
    {
        return OrderPayment::withoutGlobalScopes()->where('restaurant_id', $restaurant->id)->where('method', 'online')->where('status', OrderPayment::PAID)
            ->where('commission_cents', '>', 0)->whereNull('commission_billed_at')
            ->whereHas('order', fn ($q) => $q->withoutGlobalScopes()->where('currency_code', $currency))->get(['id', 'commission_cents']);
    }
}
