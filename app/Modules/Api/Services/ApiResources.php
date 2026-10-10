<?php

namespace App\Modules\Api\Services;

use App\Modules\Marketing\Models\Customer;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Reservations\Models\Reservation;
use App\Modules\Tenancy\Models\Restaurant;

/** The JSON shape of orders and dishes in the API and in webhooks. Money is always in minor units (cents). */
class ApiResources
{
    /** @return array<string, mixed> */
    public function order(Order $order, bool $withItems = true): array
    {
        $data = [
            'id' => $order->id, 'number' => $order->number, 'status' => $order->status, 'type' => $order->type, 'source' => $order->source,
            'table' => $order->table_name, 'branch_id' => $order->branch_id,
            'customer' => ['name' => $order->customer_name, 'phone' => $order->customer_phone, 'email' => $order->customer_email],
            'delivery_address' => $order->delivery_address, 'note' => $order->note,
            'currency' => $order->currency_code, 'subtotal_cents' => $order->subtotal_cents, 'discount_cents' => $order->discount_cents, 'service_cents' => $order->service_cents,
            'delivery_cents' => $order->delivery_cents, 'tax_cents' => $order->tax_cents, 'total_cents' => $order->total_cents,
            'payment_method' => $order->payment_method, 'paid' => $order->paid_at !== null, 'promo_code' => $order->promo_code,
            'created_at' => $order->created_at?->toIso8601String(), 'paid_at' => $order->paid_at?->toIso8601String(),
        ];

        if ($withItems) {
            $data['items'] = $order->items->map(fn ($i) => [
                'name' => $i->name, 'qty' => $i->qty, 'unit_cents' => $i->unit_cents, 'total_cents' => $i->total_cents, 'note' => $i->note,
                'options' => collect((array) $i->options)->map(fn ($o) => ['group' => $o['group'] ?? null, 'name' => $o['name'] ?? null, 'price_delta_cents' => $o['price_delta_cents'] ?? 0])->all(),
            ])->all();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public function reservation(Reservation $r): array
    {
        return [
            'id' => $r->id, 'status' => $r->status, 'name' => $r->name, 'phone' => $r->phone, 'email' => $r->email, 'party_size' => $r->party_size,
            'starts_at' => $r->starts_at?->toIso8601String(), 'duration_minutes' => $r->duration_minutes, 'table_id' => $r->table_id, 'note' => $r->note, 'source' => $r->source,
            'created_at' => $r->created_at?->toIso8601String(),
            'deposit_cents' => $r->deposit_cents, 'deposit_status' => $r->deposit_status,
            // Guests manage (and pay a deposit for) their booking here.
            'manage_url' => ($restaurant = Restaurant::find($r->restaurant_id))?->publicUrl('reserve/'.$r->token),
        ];
    }

    /** @return array<string, mixed> */
    public function customer(Customer $c): array
    {
        return [
            'id' => $c->id, 'name' => $c->name, 'email' => $c->email, 'phone' => $c->phone, 'orders_count' => $c->orders_count, 'total_cents' => $c->total_cents,
            'marketing_opt_in' => (bool) $c->marketing_opt_in, 'first_order_at' => $c->first_order_at?->toIso8601String(), 'last_order_at' => $c->last_order_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function product(Product $p): array
    {
        return [
            'id' => $p->id, 'category_id' => $p->category_id, 'name' => $p->name, 'description' => $p->description, 'price' => (float) $p->price,
            'is_active' => (bool) $p->is_active, 'is_available' => (bool) $p->is_available, 'stock_qty' => $p->stock_qty,
        ];
    }

    /** @return array<string, mixed> */
    public function category(Category $c): array
    {
        return ['id' => $c->id, 'name' => $c->name, 'is_active' => (bool) $c->is_active, 'sort' => $c->sort];
    }
}
