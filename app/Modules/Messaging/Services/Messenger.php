<?php

namespace App\Modules\Messaging\Services;

use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Marketing\Services\MarketingSettings;
use App\Modules\Messaging\Models\MessageLog;
use App\Modules\Tenancy\Models\Restaurant;
use Throwable;

/**
 * The one way the rest of the script sends an SMS or WhatsApp message. It never throws: a missing provider, a bad number,
 * an used-up monthly allowance or a provider outage just means the message is not sent (and is logged as such).
 */
class Messenger
{
    public function __construct(
        private readonly MessagingManager $providers,
        private readonly LimitGuard $limits,
        private readonly MarketingSettings $marketing,
    ) {}

    public function available(string $channel): bool
    {
        return $this->providers->available($channel);
    }

    /** @return bool true when the provider accepted the message */
    public function send(?Restaurant $restaurant, string $channel, string $to, string $text, string $purpose = 'notice'): bool
    {
        $provider = $this->providers->forChannel($channel);
        $number = $this->normalize($to, $restaurant);

        if (! $provider || $number === null) {
            return false; // nothing to log: this restaurant never asked for it, or the number is unusable
        }

        if ($restaurant && ! $this->hasAllowance($restaurant)) {
            $this->log($restaurant, $channel, $provider->code(), $number, $purpose, 'skipped', 'monthly allowance used');

            return false;
        }

        try {
            $provider->send($channel, $number, mb_substr($text, 0, 1000), $this->providers->config($provider));
            $this->log($restaurant, $channel, $provider->code(), $number, $purpose, 'sent');

            return true;
        } catch (Throwable $e) {
            $this->log($restaurant, $channel, $provider->code(), $number, $purpose, 'failed', mb_substr($e->getMessage(), 0, 200));

            return false;
        }
    }

    /** Messages sent this month, and the plan's allowance (null = unlimited). @return array{used: int, limit: ?int} */
    public function usage(Restaurant $restaurant): array
    {
        return [
            'used' => MessageLog::where('restaurant_id', $restaurant->id)->where('status', 'sent')->where('created_at', '>=', now()->startOfMonth())->count(),
            'limit' => $this->limits->limit($restaurant, 'messages'),
        ];
    }

    public function hasAllowance(Restaurant $restaurant): bool
    {
        $u = $this->usage($restaurant);

        return $u['limit'] === null || $u['used'] < $u['limit'];
    }

    /**
     * "+90 532 111 22 33", "0090 532…" and, with the restaurant's country code set, "0532 111 22 33" all become "+905321112233".
     * Anything that is not a plausible international number gives null.
     */
    public function normalize(string $raw, ?Restaurant $restaurant = null): ?string
    {
        $raw = trim($raw);
        $digits = preg_replace('/\D+/', '', $raw);

        if (str_starts_with($raw, '+')) {
            // already international
        } elseif (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif ($restaurant && ($code = preg_replace('/\D+/', '', (string) $this->marketing->get($restaurant, 'calling_code'))) !== '') {
            $digits = $code.ltrim($digits, '0');
        } else {
            return null; // a local number with no country: sending it would reach a stranger or nobody
        }

        return preg_match('/^[1-9]\d{7,14}$/', $digits) ? '+'.$digits : null;
    }

    private function log(?Restaurant $restaurant, string $channel, string $provider, string $number, string $purpose, string $status, ?string $error = null): void
    {
        MessageLog::create([
            'restaurant_id' => $restaurant?->id, 'channel' => $channel, 'provider' => $provider, 'purpose' => $purpose,
            'recipient' => str_repeat('•', max(0, strlen($number) - 4)).substr($number, -4), 'status' => $status, 'error' => $error,
        ]);
    }
}
