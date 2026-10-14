<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Marketing\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Keeps one record per person per restaurant, built from their orders. Marketing consent is never assumed:
 * it only turns on when the guest ticked the box at checkout.
 */
class CustomerService
{
    /** Links the order to its customer (creating or updating the record) and refreshes the totals. */
    public function recordOrder(Order $order): ?Customer
    {
        $email = $order->customer_email ? Str::lower($order->customer_email) : null;
        $phoneKey = $this->phoneKey($order->customer_phone);

        // Dine-in guests who gave nothing leave no record.
        if ($email === null && $phoneKey === null) {
            return null;
        }

        $customer = ($email ? Customer::where('email', $email)->first() : null)
            ?? ($phoneKey ? Customer::where('phone_key', $phoneKey)->first() : null)
            ?? new Customer;

        $customer->fill([
            'name' => $order->customer_name ?: $customer->name,
            'email' => $email ?? $customer->email,
            'phone' => $order->customer_phone ?: $customer->phone,
            'phone_key' => $phoneKey ?? $customer->phone_key,
            'locale' => $order->locale ?: $customer->locale,
        ]);

        if ($order->marketing_opt_in && $customer->email) {
            // A fresh yes after an unsubscribe is a new consent.
            if (! $customer->marketing_opt_in || $customer->unsubscribed_at) {
                $customer->opted_in_at = now();
            }
            $customer->marketing_opt_in = true;
            $customer->unsubscribed_at = null;
        }

        $customer->save();
        $order->forceFill(['customer_id' => $customer->id])->saveQuietly();

        return $this->refresh($customer);
    }

    /** Recomputes order count, spend and dates from the customer's orders (cancelled ones do not count). */
    public function refresh(Customer $customer): Customer
    {
        $stats = Order::where('customer_id', $customer->id)->where('status', '!=', OrderStatus::CANCELLED)
            ->selectRaw('count(*) as n, coalesce(sum(total_cents), 0) as spent, min(created_at) as first_at, max(created_at) as last_at')->first();

        $customer->forceFill([
            'orders_count' => (int) $stats->n, 'total_cents' => (int) $stats->spent,
            'first_order_at' => $stats->first_at, 'last_order_at' => $stats->last_at,
        ])->save();

        return $customer;
    }

    public function unsubscribe(Customer $customer): void
    {
        $customer->forceFill(['marketing_opt_in' => false, 'unsubscribed_at' => now()])->save();
    }

    /** Right to erasure: the record goes, and the personal details are wiped from their orders (the sales stay). */
    public function forget(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            Order::where('customer_id', $customer->id)->update(['customer_name' => null, 'customer_phone' => null, 'customer_email' => null, 'delivery_address' => null, 'customer_id' => null]);
            $customer->delete();
        });
    }

    /** Digits only, so "+90 (532) 111-22-33" and "05321112233" are recognised as one number (last 10 digits). */
    public function phoneKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return strlen($digits) >= 6 ? substr($digits, -10) : null;
    }
}
