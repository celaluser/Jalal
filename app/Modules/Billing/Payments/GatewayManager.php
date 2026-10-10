<?php

namespace App\Modules\Billing\Payments;

use App\Modules\Billing\Contracts\PaymentGatewayInterface;
use App\Modules\Billing\Gateways\BankTransferGateway;
use App\Modules\Billing\Gateways\FlutterwaveGateway;
use App\Modules\Billing\Gateways\IyzicoGateway;
use App\Modules\Billing\Gateways\MercadoPagoGateway;
use App\Modules\Billing\Gateways\MidtransGateway;
use App\Modules\Billing\Gateways\MollieGateway;
use App\Modules\Billing\Gateways\PaddleGateway;
use App\Modules\Billing\Gateways\EpointGateway;
use App\Modules\Billing\Gateways\PayPalGateway;
use App\Modules\Billing\Gateways\PaytrGateway;
use App\Modules\Billing\Gateways\PaystackGateway;
use App\Modules\Billing\Gateways\RazorpayGateway;
use App\Modules\Billing\Gateways\StripeGateway;
use App\Modules\Core\Services\SettingsService;

/**
 * Registry of payment gateways plus their admin-managed configuration. Settings keys:
 * payments.{code}.enabled and payments.{code}.{field}; secret fields are stored encrypted.
 * Add-ons add their own gateway with register().
 */
class GatewayManager
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $gateways = [];

    public function __construct(private readonly SettingsService $settings)
    {
        foreach ([new StripeGateway, new PayPalGateway, new RazorpayGateway, new PaystackGateway, new FlutterwaveGateway, new MollieGateway, new IyzicoGateway, new PaddleGateway, new MercadoPagoGateway, new MidtransGateway, new PaytrGateway, new EpointGateway, new BankTransferGateway] as $gateway) {
            $this->register($gateway);
        }
    }

    public function register(PaymentGatewayInterface $gateway): void
    {
        $this->gateways[$gateway->code()] = $gateway;
    }

    /** @return array<string, PaymentGatewayInterface> */
    public function all(): array
    {
        return $this->gateways;
    }

    public function find(string $code): ?PaymentGatewayInterface
    {
        return $this->gateways[$code] ?? null;
    }

    public function isEnabled(string $code): bool
    {
        return (bool) $this->settings->get("payments.{$code}.enabled", false);
    }

    public function config(string $code): GatewayConfig
    {
        $values = [];

        foreach ($this->gateways[$code]->fields() as $field) {
            $values[$field['key']] = $this->settings->get("payments.{$code}.{$field['key']}");
        }

        return new GatewayConfig($values);
    }

    /** Enabled and every required field filled in. */
    public function isReady(string $code): bool
    {
        if (! isset($this->gateways[$code]) || ! $this->isEnabled($code)) {
            return false;
        }

        $config = $this->config($code);

        foreach ($this->gateways[$code]->fields() as $field) {
            if (empty($field['optional']) && $config->get($field['key']) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gateways a customer may pay with in this currency.
     *
     * @return array<string, PaymentGatewayInterface>
     */
    public function availableFor(string $currency): array
    {
        return array_filter($this->gateways, fn (PaymentGatewayInterface $g) => $this->isReady($g->code()) && $g->supportsCurrency($currency));
    }

    /**
     * Persist admin input. A blank secret keeps the stored one, so re-saving the form never wipes keys.
     *
     * @param  array<string, string|null>  $input
     */
    public function save(string $code, bool $enabled, array $input): void
    {
        $this->settings->set("payments.{$code}.enabled", $enabled ? '1' : '0');

        foreach ($this->gateways[$code]->fields() as $field) {
            // A field that was not submitted at all is left alone (partial update).
            if (! array_key_exists($field['key'], $input)) {
                continue;
            }

            $value = $input[$field['key']];

            if ($field['type'] === 'secret' && ($value === null || $value === '')) {
                continue;
            }

            $this->settings->set("payments.{$code}.{$field['key']}", $value, encrypt: $field['type'] === 'secret');
        }
    }
}
