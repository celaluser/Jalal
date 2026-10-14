<?php

namespace App\Modules\Orders\Services;

use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Product;

/**
 * E-mails the owner when a tracked dish drops to its "warn at" level or runs out, once per dip: topping the stock up
 * above the level re-arms it. Called after stock was taken by an order.
 */
class LowStockAlerts
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** @param list<int> $productIds dishes whose stock just went down */
    public function check(array $productIds): void
    {
        $restaurant = $this->tenant->get();

        if (! $restaurant || $productIds === []) {
            return;
        }

        $products = Product::whereIn('id', $productIds)->whereNotNull('stock_qty')->whereNotNull('low_stock_at')->get();
        $low = $products->filter(fn (Product $p) => $p->stock_qty <= $p->low_stock_at && $p->low_stock_notified_at === null);

        if ($low->isEmpty()) {
            return;
        }

        // Marked first: a failing mail server must not mean one e-mail per order.
        Product::whereIn('id', $low->pluck('id'))->update(['low_stock_notified_at' => now()]);

        $email = $restaurant->owner?->email;
        $locale = $restaurant->locale;
        $list = $low->map(fn (Product $p) => $p->tr('name', $locale, $restaurant->locale).' ('.($p->stock_qty > 0 ? __('menu.low_stock', ['count' => $p->stock_qty]) : __('menu.out_of_stock')).')')->implode(', ');

        SafeMail::send($email, new TemplatedMail('low_stock', ['restaurant' => $restaurant->name, 'items' => $list, 'stock_url' => url('/menu/stock')], $locale));
    }
}
