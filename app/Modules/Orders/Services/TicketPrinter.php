<?php

namespace App\Modules\Orders\Services;

use App\Modules\Core\Services\SettingsService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PrintJob;
use App\Modules\Orders\Support\EscPos;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Str;

/**
 * Kitchen tickets and receipts for thermal printers. Tickets are queued; a small "bridge" program next to the printer asks
 * for the next one (see docs/tools/print-bridge.php), so the cloud site never has to reach into the restaurant's network.
 */
class TicketPrinter
{
    public function __construct(private readonly OrderSettings $settings, private readonly SettingsService $store) {}

    // ---- queue --------------------------------------------------------------------------

    public function enqueue(Order $order, string $kind): PrintJob
    {
        $restaurant = $order->restaurant ?? Restaurant::withoutGlobalScopes()->find($order->restaurant_id);
        $s = $this->settings->for($restaurant);
        $bytes = $kind === 'receipt' ? $this->receipt($order, $restaurant, (int) $s['print_width'], (string) $s['print_codepage']) : $this->kitchen($order, $restaurant, (int) $s['print_width'], (string) $s['print_codepage']);

        return PrintJob::create(['order_id' => $order->id, 'kind' => $kind, 'payload' => base64_encode($bytes), 'restaurant_id' => $order->restaurant_id]);
    }

    /** The bridge's secret address part. Made on first use; regenerating it cuts off a bridge that should not print any more. */
    public function token(Restaurant $restaurant, bool $regenerate = false): string
    {
        $current = $this->store->get('print.token', null, $restaurant->id);

        if (! $current || $regenerate) {
            $current = $restaurant->id.'-'.Str::lower(Str::random(32));
            $this->store->set('print.token', $current, $restaurant->id, encrypt: true);
        }

        return (string) $current;
    }

    /** The restaurant a bridge token belongs to, or null. Compared in constant time. */
    public function restaurantFor(string $token): ?Restaurant
    {
        if (! preg_match('/^(\d+)-[a-z0-9]{32}$/', $token, $m)) {
            return null;
        }

        $restaurant = Restaurant::find((int) $m[1]);

        return $restaurant && hash_equals($this->token($restaurant), $token) ? $restaurant : null;
    }

    // ---- paper --------------------------------------------------------------------------

    public function kitchen(Order $order, Restaurant $restaurant, int $width = 48, string $codepage = 'cp437'): string
    {
        $order->loadMissing('items');
        $p = new EscPos($width, $codepage);
        $tz = in_array($restaurant->timezone, timezone_identifiers_list(), true) ? $restaurant->timezone : 'UTC';

        $p->align('center')->bold()->size(2)->text($order->label())->size(1)->bold(false)
            ->text(__('orders.type_'.$order->type).($order->table_name ? ' · '.table_label($order->table_name) : ''))
            ->text($order->created_at->setTimezone($tz)->format('Y-m-d H:i'));

        if ($order->scheduled_for) {
            $p->bold()->text(__('orders.scheduled_for', ['time' => $order->scheduled_for->setTimezone($tz)->format('D H:i')]))->bold(false);
        }

        $p->align('left')->rule();

        foreach ($order->items as $item) {
            $p->bold()->size(2)->wrapped($item->qty.'x '.$item->name)->size(1)->bold(false);

            if ($item->optionsLabel()) {
                $p->wrapped('+ '.$item->optionsLabel(), 2);
            }

            if ($item->note) {
                $p->bold()->wrapped('* '.$item->note, 2)->bold(false);
            }
        }

        $p->rule();

        if ($order->note) {
            $p->bold()->wrapped(__('orders.note').': '.$order->note)->bold(false)->rule();
        }

        foreach (array_filter([$order->vehicle ? __('orders.vehicle').': '.$order->vehicle : null, $order->room ? __('orders.room').' '.$order->room : null, $order->customer_name, $order->customer_phone, $order->delivery_address]) as $line) {
            $p->wrapped($line);
        }

        return $p->cut()->bytes();
    }

    public function receipt(Order $order, Restaurant $restaurant, int $width = 48, string $codepage = 'cp437'): string
    {
        $order->loadMissing(['items', 'payments']);
        $money = fn (int $c) => $restaurant->money($c / 100);
        $p = new EscPos($width, $codepage);
        $tz = in_array($restaurant->timezone, timezone_identifiers_list(), true) ? $restaurant->timezone : 'UTC';

        $p->align('center')->bold()->size(2)->text($restaurant->name)->size(1)->bold(false);

        if ($restaurant->address) {
            $p->text($restaurant->address);
        }

        $p->text(__('orders.receipt').' '.$order->label())->text($order->created_at->setTimezone($tz)->format('Y-m-d H:i'))->align('left')->rule();

        foreach ($order->items as $item) {
            $p->columns($item->qty.'x '.$item->name, $money($item->total_cents));

            if ($item->optionsLabel()) {
                $p->wrapped('+ '.$item->optionsLabel(), 3);
            }
        }

        $p->rule()->columns(__('orders.subtotal'), $money($order->subtotal_cents));

        foreach ([
            [__('marketing.promo_discount'), -$order->discount_cents], [__('orders.discount'), -$order->manual_discount_cents], [__('orders.service'), $order->service_cents],
            [__('orders.packaging'), $order->packaging_cents], [__('orders.delivery_fee'), $order->delivery_cents], [__('orders.tax'), $order->tax_cents],
        ] as [$label, $cents]) {
            if ($cents !== 0) {
                $p->columns($label, ($cents < 0 ? '-' : '').$money(abs($cents)));
            }
        }

        $p->bold()->size(2)->columns(__('orders.total'), $money($order->total_cents))->size(1)->bold(false);

        if ($order->tip_cents) {
            $p->columns(__('orders.tip'), $money($order->tip_cents));
        }

        $p->rule()->align('center')->text($order->isPaid() ? __('orders.paid') : __('orders.unpaid'))->text(__('orders.receipt_thanks'))->feed()
            ->qr($restaurant->publicUrl('order/'.$order->token.'/receipt'))->text(__('orders.receipt_scan'));

        return $p->cut()->bytes();
    }
}
