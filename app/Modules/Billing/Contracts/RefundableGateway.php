<?php

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Payments\GatewayConfig;

/** A gateway that can give money back through its API. Others are refunded by hand in the gateway's own dashboard. */
interface RefundableGateway
{
    /**
     * @param  string  $transactionId  the id returned with the successful payment
     * @param  int  $amountMinor  amount in the currency's minor units
     * @return string the gateway's refund id
     *
     * @throws GatewayException
     */
    public function refund(string $transactionId, int $amountMinor, string $currency, GatewayConfig $config): string;
}
