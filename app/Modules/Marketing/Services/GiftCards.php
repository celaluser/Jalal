<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Marketing\Models\GiftCard;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Str;

/** Gift cards sold or given by the restaurant. A guest types the code at checkout; the balance is spent down. */
class GiftCards
{
    public function issue(Restaurant $restaurant, int $cents, ?string $email = null, ?string $note = null, ?int $days = null): GiftCard
    {
        // The balance is guarded against mass assignment (only spend()/refund() change it), so it is set explicitly.
        $card = (new GiftCard([
            'code' => $this->freshCode(), 'initial_cents' => $cents, 'recipient_email' => $email, 'note' => $note, 'expires_at' => $days ? now()->addDays($days) : null,
        ]))->forceFill(['balance_cents' => $cents]);
        $card->save();

        if ($email) {
            SafeMail::send($email, new TemplatedMail('gift_card', [
                'restaurant' => $restaurant->name, 'code' => $card->code, 'amount' => $restaurant->money($cents / 100), 'note' => (string) $note, 'menu_url' => $restaurant->publicUrl(),
            ], $restaurant->locale));
        }

        return $card;
    }

    public function find(?string $code): ?GiftCard
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string) $code));

        return $code !== '' ? GiftCard::where('code', $code)->first() : null;
    }

    /** Takes $cents off the balance; false when the balance is no longer enough (another order used it first). */
    public function spend(GiftCard $card, int $cents): bool
    {
        return GiftCard::whereKey($card->id)->where('balance_cents', '>=', $cents)->decrement('balance_cents', $cents) === 1;
    }

    public function refund(GiftCard $card, int $cents): void
    {
        GiftCard::whereKey($card->id)->increment('balance_cents', $cents);
    }

    private function freshCode(): string
    {
        do {
            $code = 'GIFT-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (GiftCard::where('code', $code)->exists());

        return $code;
    }
}
