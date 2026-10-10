<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Marketing\Models\PromoCode;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Str;

/**
 * "Order five times, get 10% off": after every Nth completed order the guest earns a single-use promo code
 * that only they can redeem. It is shown on the order page and sent by e-mail. Nothing is redeemed by phone number
 * alone at the counter: the code is entered at checkout like any other.
 */
class LoyaltyService
{
    public function __construct(private readonly MarketingSettings $settings) {}

    /** Creates the reward when this completed order is the Nth. Safe to call twice for the same order. */
    public function onCompleted(Restaurant $restaurant, Order $order): ?PromoCode
    {
        $s = $this->settings->for($restaurant);
        $customer = $order->customer_id ? Customer::find($order->customer_id) : null;

        if (! $s['loyalty_enabled'] || ! $customer) {
            return null;
        }

        $every = max(2, (int) $s['loyalty_every']);
        $completed = Order::where('customer_id', $customer->id)->where('status', OrderStatus::COMPLETED)->count();

        if ($completed === 0 || $completed % $every !== 0 || PromoCode::where('source_order_id', $order->id)->exists()) {
            return null;
        }

        $percent = $s['loyalty_reward_type'] === PromoCode::PERCENT;
        $promo = PromoCode::create([
            'code' => $this->freshCode(), 'description' => 'Loyalty reward', 'type' => $s['loyalty_reward_type'],
            'value' => $percent ? min(100, (int) round((float) $s['loyalty_reward_value'])) : (int) round((float) $s['loyalty_reward_value'] * 100),
            'max_uses' => 1, 'ends_at' => now()->addDays((int) $s['loyalty_valid_days']),
            'customer_id' => $customer->id, 'source_order_id' => $order->id,
        ]);

        if ($customer->email) {
            SafeMail::send($customer->email, new TemplatedMail('loyalty_reward', [
                'name' => $customer->name ?: '', 'restaurant' => $restaurant->name, 'code' => $promo->code, 'reward' => $this->describe($restaurant, $promo),
                'expires' => $promo->ends_at->toFormattedDateString(), 'orders' => (string) $completed,
                'menu_url' => $restaurant->publicUrl(),
            ], $customer->locale));
        }

        return $promo;
    }

    /** The reward this order earned, if any (shown on the guest's order page). */
    public function rewardFor(Order $order): ?PromoCode
    {
        return PromoCode::where('source_order_id', $order->id)->first();
    }

    /** "10% off" / "$5.00 off". */
    public function describe(Restaurant $restaurant, PromoCode $promo): string
    {
        return $promo->type === PromoCode::PERCENT
            ? __('marketing.reward_percent', ['value' => $promo->value])
            : __('marketing.reward_fixed', ['amount' => $restaurant->money($promo->value / 100)]);
    }

    private function freshCode(): string
    {
        do {
            $code = 'THANKS-'.strtoupper(Str::random(6));
        } while (PromoCode::where('code', $code)->exists());

        return $code;
    }
}
