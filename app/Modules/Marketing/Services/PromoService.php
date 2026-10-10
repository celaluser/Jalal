<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Marketing\Models\Customer;
use App\Modules\Marketing\Models\GiftCard;
use App\Modules\Marketing\Models\PromoCode;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Tenancy\Models\Restaurant;

/** The restaurant's own discount codes: checking them against an order and counting their use. */
class PromoService
{
    public function __construct(private readonly CustomerService $customers, private readonly GiftCards $gifts) {}

    /**
     * @return array{promo: PromoCode, discount_cents: int}
     *
     * @throws OrderException promo_invalid | promo_expired | promo_used | promo_min
     */
    public function apply(Restaurant $restaurant, ?string $code, int $subtotalCents, ?string $email = null, ?string $phone = null): array
    {
        $promo = PromoCode::where('code', PromoCode::normalize($code))->first();

        // Not a promo code? Then it may be a gift card: it behaves like a fixed discount that is limited by its balance.
        if (! $promo && ($card = $this->gifts->find($code))) {
            return $this->fromGiftCard($card, $subtotalCents);
        }

        // Reward codes belong to one person: anyone else sees the same answer as for a code that does not exist.
        if (! $promo || ! $promo->is_active || ($promo->customer_id && ! $this->belongsTo($promo, $email, $phone))) {
            throw new OrderException('promo_invalid');
        }

        if (($promo->starts_at && $promo->starts_at->isFuture()) || ($promo->ends_at && $promo->ends_at->isPast())) {
            throw new OrderException('promo_expired');
        }

        if ($promo->exhausted()) {
            throw new OrderException('promo_used');
        }

        if ($subtotalCents < $promo->min_order_cents) {
            throw new OrderException('promo_min');
        }

        return ['promo' => $promo, 'discount_cents' => $this->discount($promo, $subtotalCents)];
    }

    /**
     * A gift card is wrapped in an unsaved PromoCode so the order flow can treat both alike.
     *
     * @return array{promo: PromoCode, discount_cents: int}
     */
    private function fromGiftCard(GiftCard $card, int $subtotalCents): array
    {
        if (! $card->usable()) {
            throw new OrderException($card->balance_cents <= 0 ? 'promo_used' : 'promo_expired');
        }

        $amount = min($card->balance_cents, $subtotalCents);
        $promo = new PromoCode(['code' => $card->code, 'type' => PromoCode::FIXED, 'value' => $amount]);
        $promo->setAttribute('gift_card_id', $card->id);

        return ['promo' => $promo, 'discount_cents' => $amount];
    }

    public function discount(PromoCode $promo, int $subtotalCents): int
    {
        $amount = $promo->type === PromoCode::PERCENT ? (int) round($subtotalCents * min(100, $promo->value) / 100) : $promo->value;

        return max(0, min($amount, $subtotalCents));
    }

    /** Counts one use; false when the last use was taken a moment ago by someone else. */
    public function redeem(PromoCode $promo): bool
    {
        if ($id = $promo->getAttribute('gift_card_id')) {
            return $this->gifts->spend(GiftCard::findOrFail($id), $promo->value);
        }

        return PromoCode::whereKey($promo->id)
            ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses'))
            ->increment('uses_count') === 1;
    }

    /** Gives a use back when the order it was taken for could not be saved. */
    public function release(PromoCode $promo): void
    {
        if ($id = $promo->getAttribute('gift_card_id')) {
            $this->gifts->refund(GiftCard::findOrFail($id), $promo->value);

            return;
        }

        PromoCode::whereKey($promo->id)->where('uses_count', '>', 0)->decrement('uses_count');
    }

    private function belongsTo(PromoCode $promo, ?string $email, ?string $phone): bool
    {
        $customer = Customer::find($promo->customer_id);

        if (! $customer) {
            return false;
        }

        $key = $this->customers->phoneKey($phone);

        return ($email && $customer->email && strcasecmp(trim($email), $customer->email) === 0) || ($key && $customer->phone_key === $key);
    }
}
