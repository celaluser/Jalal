<?php

namespace App\Modules\Orders\Services;

use App\Models\User;
use App\Modules\Marketing\Models\PromoCode;
use App\Modules\Marketing\Services\PromoService;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderEvent;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Orders\Support\OrderType;
use App\Modules\Storefront\Services\CartPricing;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Everything that happens to an order: placing it from a guest's cart, moving it through the
 * kitchen, taking payment, cancelling. Runs inside the restaurant's tenant context.
 *
 * The browser never supplies a price or a name: the cart is re-priced here (CartPricing).
 */
class OrderService
{
    public function __construct(
        private readonly CartPricing $pricing,
        private readonly OrderSettings $settings,
        private readonly OrderTotals $totals,
        private readonly PromoService $promos,
    ) {}

    /**
     * @param  array{type: string, lines: array<int, mixed>, table_id?: int|null, customer_name?: ?string, customer_phone?: ?string, customer_email?: ?string, marketing_opt_in?: bool, promo_code?: ?string, delivery_address?: ?string, note?: ?string, payment_method?: ?string, idempotency_key?: ?string, locale?: ?string}  $data
     *
     * @throws OrderException
     */
    public function place(Restaurant $restaurant, array $data, string $source = 'qr', ?User $by = null): Order
    {
        $key = isset($data['idempotency_key']) && $data['idempotency_key'] !== '' ? substr((string) $data['idempotency_key'], 0, 64) : null;

        if ($key && ($existing = Order::where('idempotency_key', $key)->first())) {
            return $existing; // the guest tapped twice, or the network retried: same order, not a second one
        }

        $settings = $this->settings->for($restaurant);
        $type = (string) ($data['type'] ?? '');
        // Staff keying in an order at the counter or table are not held to the rules made for guests
        // (online ordering paused, name/phone required, payment methods offered online).
        $staff = $source === 'staff';

        if (! $staff && ! $this->settings->accepting($restaurant)) {
            throw new OrderException('closed');
        }

        if (! in_array($type, $staff ? OrderType::ALL : $this->settings->types($restaurant), true)) {
            throw new OrderException('type_unavailable');
        }

        $table = $this->resolveTable($type, $data, $settings);
        $phone = $this->clean($data['customer_phone'] ?? null, 40);
        $name = $this->clean($data['customer_name'] ?? null, 80);
        $address = $this->clean($data['delivery_address'] ?? null, 255);
        $email = $this->clean($data['customer_email'] ?? null, 190);

        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new OrderException('email_invalid');
        }

        if ($phone !== null && ! preg_match('/^[0-9+()\-\s.]{6,40}$/', $phone)) {
            throw new OrderException('phone_required');
        }

        if (! $staff && $type !== OrderType::DINE_IN && $phone === null) {
            throw new OrderException('phone_required');
        }

        if (! $staff && ($settings['require_name'] || $type !== OrderType::DINE_IN) && $name === null) {
            throw new OrderException('name_required');
        }

        if ($type === OrderType::DELIVERY && ($address === null || mb_strlen($address) < 5)) {
            throw new OrderException('address_required');
        }

        $method = (string) ($data['payment_method'] ?? '');

        if (! in_array($method, $staff ? ['cash', 'card'] : $this->settings->paymentMethods($restaurant), true)) {
            throw new OrderException('payment_unavailable');
        }

        $quote = $this->pricing->quote($restaurant, $data['lines'] ?? []);

        if (! $quote['valid']) {
            throw new OrderException('cart_invalid', $quote['lines']);
        }

        if (! $staff && $type === OrderType::DELIVERY && $quote['subtotal_cents'] < $this->settings->cents($restaurant, 'delivery_min')) {
            throw new OrderException('below_minimum');
        }

        $promo = null;
        $discount = 0;

        if (! empty($data['promo_code'])) {
            ['promo' => $promo, 'discount_cents' => $discount] = $this->promos->apply($restaurant, (string) $data['promo_code'], $quote['subtotal_cents'], $email, $phone);
        }

        $sums = $this->totals->compute($quote['subtotal_cents'], $type, $settings, $discount);

        $order = $this->store($restaurant, $settings, $quote, $sums, [
            'type' => $type, 'source' => $source, 'idempotency_key' => $key,
            'table_id' => $table?->id, 'table_name' => $table?->name,
            'customer_name' => $name, 'customer_phone' => $phone, 'customer_email' => $email !== null ? mb_strtolower($email) : null, 'delivery_address' => $type === OrderType::DELIVERY ? $address : null,
            'note' => $this->clean($data['note'] ?? null, 300), 'locale' => $data['locale'] ?? app()->getLocale(),
            'payment_method' => $method, 'currency_code' => $restaurant->currency_code,
            'marketing_opt_in' => $email !== null && ! empty($data['marketing_opt_in']), 'promo_code' => $promo?->code,
        ], $by, $promo);

        event(new OrderPlaced($order));

        return $order;
    }

    /** @throws OrderException */
    public function transition(Order $order, string $to, ?User $by = null, ?string $reason = null): Order
    {
        $from = $order->status;

        if (! OrderStatus::canMove($from, $to)) {
            throw new OrderException('invalid_transition');
        }

        DB::transaction(function () use ($order, $to, $by, $reason, $from) {
            $order->forceFill(['status' => $to]);

            match ($to) {
                OrderStatus::ACCEPTED => $order->accepted_at ??= now(),
                OrderStatus::PREPARING => $order->accepted_at ??= now(),
                OrderStatus::READY => $order->ready_at = now(),
                OrderStatus::COMPLETED => $order->completed_at = now(),
                OrderStatus::CANCELLED => [$order->cancelled_at = now(), $order->cancel_reason = $this->clean($reason, 200)],
                default => null,
            };

            $order->save();
            $this->log($order, 'status', $from, $to, $reason, $by);
        });

        event(new OrderStatusChanged($order, $from, $to));

        return $order;
    }

    /** Record that the guest paid on the spot (cash or card terminal). */
    public function markPaid(Order $order, string $method, ?User $by = null): Order
    {
        if ($order->isPaid() || $order->status === OrderStatus::CANCELLED) {
            throw new OrderException('cannot_pay');
        }

        if (! in_array($method, ['cash', 'card'], true)) {
            throw new OrderException('payment_unavailable');
        }

        $order->forceFill(['payment_method' => $method, 'paid_at' => now()])->save();
        $this->log($order, 'payment', null, null, $method, $by);

        return $order;
    }

    /**
     * @param  array{lines: list<array<string, mixed>>, subtotal_cents: int}  $quote
     * @param  array{subtotal: int, service: int, delivery: int, tax: int, total: int}  $sums
     * @param  array<string, mixed>  $attributes
     */
    private function store(Restaurant $restaurant, array $settings, array $quote, array $sums, array $attributes, ?User $by, ?PromoCode $promo = null): Order
    {
        // An order a staff member typed in is already confirmed by that person.
        $auto = (bool) $settings['auto_accept'] || ($attributes['source'] ?? '') === 'staff';

        // Two orders arriving in the same instant can pick the same number; the unique index catches it and we try the next.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($settings, $quote, $sums, $attributes, $by, $auto, $promo) {
                    $order = new Order($attributes);
                    $order->forceFill([
                        'number' => (int) Order::max('number') > 0 ? (int) Order::max('number') + 1 : 1001,
                        'token' => Str::lower(Str::random(24)),
                        'status' => $auto ? OrderStatus::ACCEPTED : OrderStatus::NEW,
                        'subtotal_cents' => $sums['subtotal'], 'discount_cents' => $sums['discount'], 'service_cents' => $sums['service'], 'delivery_cents' => $sums['delivery'],
                        'tax_cents' => $sums['tax'], 'total_cents' => $sums['total'],
                        'prep_minutes' => (int) $settings['prep_minutes'], 'accepted_at' => $auto ? now() : null,
                    ]);
                    $order->save();

                    // Taken inside the transaction: if the last use went to someone else a moment ago, nothing is saved.
                    if ($promo && ! $this->promos->redeem($promo)) {
                        throw new OrderException('promo_used');
                    }

                    foreach ($quote['lines'] as $line) {
                        $order->items()->create([
                            'product_id' => $line['product_id'], 'name' => $line['name'],
                            'options' => array_map(fn ($o) => ['group' => $o['group'], 'name' => $o['name'], 'price_delta_cents' => $o['price_delta_cents']], $line['options']),
                            'note' => $line['note'] !== '' ? $line['note'] : null, 'qty' => $line['qty'], 'unit_cents' => $line['unit_cents'], 'total_cents' => $line['total_cents'],
                        ]);
                    }

                    $this->log($order, 'placed', null, OrderStatus::NEW, null, $by);

                    if ($auto) {
                        $this->log($order, 'status', OrderStatus::NEW, OrderStatus::ACCEPTED, null, null);
                    }

                    return $order;
                });
            } catch (UniqueConstraintViolationException $e) {
                // A repeated idempotency key means a parallel duplicate request: hand back the first order.
                if (($key = $attributes['idempotency_key'] ?? null) && ($existing = Order::where('idempotency_key', $key)->first())) {
                    return $existing;
                }
            }
        }

        throw new OrderException('busy');
    }

    /** @param array<string, mixed> $settings */
    private function resolveTable(string $type, array $data, array $settings): ?DiningTable
    {
        if ($type !== OrderType::DINE_IN) {
            return null;
        }

        $id = (int) ($data['table_id'] ?? 0);
        $table = $id ? DiningTable::where('id', $id)->where('is_active', true)->first() : null;

        if (! $table) {
            throw new OrderException('table_required');
        }

        return $table;
    }

    private function log(Order $order, string $type, ?string $from, ?string $to, ?string $note, ?User $by): void
    {
        OrderEvent::create(['order_id' => $order->id, 'user_id' => $by?->id, 'type' => $type, 'from' => $from, 'to' => $to, 'note' => $this->clean($note, 200)]);
    }

    private function clean(?string $value, int $max): ?string
    {
        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
