<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Models\OrderEvent;
use App\Modules\Orders\Models\OrderPayment;
use App\Modules\Orders\Models\Shift;

/** What a cashier took during a shift, and how much cash should be in the drawer because of it. */
class ShiftReport
{
    /**
     * @return array{methods: array<string, array{count: int, amount: int, tips: int}>, refunds: array<string, int>, cash_in: int, cash_out: int, expected: int, orders: int, tips: int}
     */
    public function build(Shift $shift): array
    {
        $until = $shift->closed_at ?? now();
        $payments = OrderPayment::where('user_id', $shift->user_id)->where('status', OrderPayment::PAID)->whereBetween('paid_at', [$shift->opened_at, $until])->get();

        $methods = [];

        foreach ($payments->groupBy('method') as $method => $rows) {
            $methods[$method] = ['count' => $rows->count(), 'amount' => (int) $rows->sum('amount_cents'), 'tips' => (int) $rows->sum('tip_cents')];
        }

        // Refunds are events ("500 cash"): the money that left the drawer is the cash ones.
        $refunds = [];

        foreach (OrderEvent::where('type', 'refund')->where('user_id', $shift->user_id)->whereBetween('created_at', [$shift->opened_at, $until])->get() as $event) {
            if (preg_match('/^(\d+) (cash|card|online)/', (string) $event->note, $m)) {
                $refunds[$m[2]] = ($refunds[$m[2]] ?? 0) + (int) $m[1];
            }
        }

        $cashIn = ($methods['cash']['amount'] ?? 0) + ($methods['cash']['tips'] ?? 0);
        $cashOut = $refunds['cash'] ?? 0;

        return [
            'methods' => $methods, 'refunds' => $refunds, 'cash_in' => $cashIn, 'cash_out' => $cashOut,
            'expected' => (int) $shift->opening_cents + $cashIn - $cashOut,
            'orders' => $payments->pluck('order_id')->unique()->count(), 'tips' => (int) $payments->sum('tip_cents'),
        ];
    }
}
