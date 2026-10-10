<?php

namespace App\Modules\Orders\Services;

use App\Modules\Billing\Contracts\PaymentGatewayInterface;
use App\Modules\Billing\Payments\GatewayConfig;
use App\Modules\Billing\Payments\GatewayManager;
use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Models\Restaurant;

/**
 * The payment gateways a restaurant takes its guests' money with, using the restaurant's OWN accounts
 * (money goes straight to them, not through the platform). The platform decides which gateways may be used and what
 * commission it keeps; the plan decides whether the restaurant may take online payments at all.
 *
 * Restaurant settings: guest_payments.{code}.enabled and guest_payments.{code}.{field}; secrets are stored encrypted.
 * Platform settings: guest_payments.enabled, guest_payments.allowed.{code}, guest_payments.commission_percent.
 */
class RestaurantGateways
{
    /** Gateways that cannot take a guest's card at the table. */
    public const EXCLUDED = ['bank_transfer', 'paddle'];

    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly SettingsService $settings,
        private readonly LimitGuard $limits,
    ) {}

    public function platformEnabled(): bool
    {
        return (bool) $this->settings->get('guest_payments.enabled', true);
    }

    public function commissionPercent(): float
    {
        return max(0.0, min(50.0, (float) $this->settings->get('guest_payments.commission_percent', 0)));
    }

    /** @return array<string, PaymentGatewayInterface> gateways the platform allows restaurants to use */
    public function allowed(): array
    {
        return array_filter($this->gateways->all(), fn (PaymentGatewayInterface $g) => ! in_array($g->code(), self::EXCLUDED, true) && (bool) $this->settings->get("guest_payments.allowed.{$g->code()}", true));
    }

    public function find(string $code): ?PaymentGatewayInterface
    {
        return $this->allowed()[$code] ?? null;
    }

    public function enabled(Restaurant $restaurant, string $code): bool
    {
        return (bool) $this->settings->get("guest_payments.{$code}.enabled", false, $restaurant->id);
    }

    public function config(Restaurant $restaurant, string $code): GatewayConfig
    {
        $values = [];

        foreach (($this->find($code)?->fields() ?? []) as $field) {
            $values[$field['key']] = $this->settings->get("guest_payments.{$code}.{$field['key']}", null, $restaurant->id);
        }

        return new GatewayConfig($values);
    }

    /** Switched on, every required key filled in, and allowed by the platform. */
    public function ready(Restaurant $restaurant, string $code): bool
    {
        $gateway = $this->find($code);

        if (! $gateway || ! $this->enabled($restaurant, $code)) {
            return false;
        }

        $config = $this->config($restaurant, $code);

        foreach ($gateway->fields() as $field) {
            if (empty($field['optional']) && $config->get($field['key']) === null) {
                return false;
            }
        }

        return true;
    }

    /** Online payments are allowed for this restaurant at all: platform switch on and the plan includes it. */
    public function permitted(Restaurant $restaurant): bool
    {
        return $this->platformEnabled() && $this->limits->hasFeature($restaurant, 'online_payments');
    }

    /** @return array<string, PaymentGatewayInterface> gateways a guest can pay with right now, in the restaurant's currency */
    public function availableFor(Restaurant $restaurant): array
    {
        if (! $this->permitted($restaurant)) {
            return [];
        }

        return array_filter($this->allowed(), fn (PaymentGatewayInterface $g) => $this->ready($restaurant, $g->code()) && $g->supportsCurrency((string) $restaurant->currency_code));
    }

    public function anyReady(Restaurant $restaurant): bool
    {
        return $this->availableFor($restaurant) !== [];
    }

    /** @param array<string, string|null> $input */
    public function save(Restaurant $restaurant, string $code, bool $enabled, array $input): void
    {
        $gateway = $this->find($code);

        if (! $gateway) {
            return;
        }

        $this->settings->set("guest_payments.{$code}.enabled", $enabled ? '1' : '0', $restaurant->id);

        foreach ($gateway->fields() as $field) {
            if (! array_key_exists($field['key'], $input)) {
                continue;
            }

            $value = $input[$field['key']];

            // A blank secret keeps the stored one, so saving the form never wipes keys.
            if ($field['type'] === 'secret' && ($value === null || $value === '')) {
                continue;
            }

            $this->settings->set("guest_payments.{$code}.{$field['key']}", $value, $restaurant->id, encrypt: $field['type'] === 'secret');
        }
    }
}
