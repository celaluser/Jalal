<?php

namespace App\Modules\Hub\Services;

use App\Modules\Hub\Models\OrderChannel;
use App\Modules\Hub\Support\ChannelRegistry;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\PaymentLedger;
use App\Modules\Orders\Support\OrderType;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/** Turns an order from another platform into a normal order: it lands on the kitchen board like any other. */
class ChannelOrders
{
    public function __construct(private readonly OrderService $orders, private readonly PaymentLedger $ledger) {}

    /**
     * @param  array<string, mixed>  $raw
     * @return array{order: Order, duplicate: bool}
     *
     * @throws InvalidArgumentException code: invalid | unmatched (with the names in the message)
     * @throws OrderException
     */
    public function receive(OrderChannel $channel, array $raw): array
    {
        $data = ChannelRegistry::get($channel->kind)->normalize($raw);

        $v = Validator::make($data, [
            'external_id' => ['required', 'string', 'max:80'],
            'type' => ['required', 'in:'.implode(',', OrderType::ALL)],
            'lines' => ['required', 'array', 'min:1', 'max:80'],
            'lines.*.product_id' => ['nullable', 'integer'], 'lines.*.name' => ['nullable', 'string', 'max:190'],
            'lines.*.qty' => ['nullable', 'integer', 'between:1,99'], 'lines.*.variant_id' => ['nullable', 'integer'],
            'lines.*.options' => ['nullable', 'array'], 'lines.*.note' => ['nullable', 'string', 'max:200'],
            'customer_name' => ['nullable', 'string', 'max:80'], 'customer_phone' => ['nullable', 'string', 'max:40'], 'customer_email' => ['nullable', 'email', 'max:190'],
            'delivery_address' => ['nullable', 'string', 'max:300'], 'note' => ['nullable', 'string', 'max:300'], 'paid' => ['nullable', 'boolean'],
        ]);

        if ($v->fails()) {
            throw new InvalidArgumentException('invalid:'.$v->errors()->first());
        }

        $data = $v->validated();

        // The same delivery twice (a retry, a double webhook) is answered with the order already made.
        if ($existing = Order::where('channel_id', $channel->id)->where('external_id', $data['external_id'])->first()) {
            return ['order' => $existing, 'duplicate' => true];
        }

        $data['lines'] = $this->match($data['lines']);
        $restaurant = $channel->restaurant;
        $data['payment_method'] = 'cash';
        $data['note'] = trim(($channel->name.' #'.$data['external_id']).(isset($data['note']) && $data['note'] !== '' ? ' · '.$data['note'] : ''));

        $order = $this->orders->place($restaurant, $data, 'hub');
        $order->forceFill(['channel_id' => $channel->id, 'external_id' => $data['external_id']])->save();

        // The platform already took the money: record it so the cashier is not asked to collect it again.
        if (! empty($data['paid'])) {
            $this->ledger->record($order, 'online', null, 0, null);
        }

        $channel->forceFill(['orders_count' => $channel->orders_count + 1, 'last_received_at' => now(), 'last_error' => null])->save();

        return ['order' => $order->refresh(), 'duplicate' => false];
    }

    /**
     * Finds each line's dish by id or by name (any language, ignoring case). Prices are never taken from the sender.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException unmatched:<names>
     */
    private function match(array $lines): array
    {
        $byName = null;
        $matched = [];
        $missing = [];

        foreach ($lines as $line) {
            $product = ! empty($line['product_id']) ? Product::find($line['product_id']) : null;

            if (! $product && ! empty($line['name'])) {
                if ($byName === null) {
                    $byName = [];

                    foreach (Product::all() as $p) {
                        foreach ((array) $p->name as $text) {
                            $byName[mb_strtolower(trim((string) $text))] ??= $p;
                        }
                    }
                }
                $product = $byName[mb_strtolower(trim((string) $line['name']))] ?? null;
            }

            if (! $product) {
                $missing[] = (string) ($line['name'] ?? $line['product_id'] ?? '?');

                continue;
            }

            $matched[] = ['product_id' => $product->id, 'variant_id' => $line['variant_id'] ?? null, 'options' => $line['options'] ?? [], 'qty' => $line['qty'] ?? 1, 'note' => $line['note'] ?? null];
        }

        if ($missing !== []) {
            throw new InvalidArgumentException('unmatched:'.implode(', ', array_unique($missing)));
        }

        return $matched;
    }
}
