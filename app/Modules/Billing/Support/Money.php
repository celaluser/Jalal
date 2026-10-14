<?php

namespace App\Modules\Billing\Support;

/**
 * Minor-unit conversion for gateways. ISO 4217 exponent: most currencies use 2 decimals, some 0
 * (JPY) and some 3 (KWD). Charging the wrong exponent charges 100x too much or too little,
 * so unknown handling must be explicit.
 */
final class Money
{
    private const ZERO_DECIMAL = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    private const THREE_DECIMAL = ['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'];

    public static function exponent(string $currency): int
    {
        $currency = strtoupper($currency);

        return match (true) {
            in_array($currency, self::ZERO_DECIMAL, true) => 0,
            in_array($currency, self::THREE_DECIMAL, true) => 3,
            default => 2,
        };
    }

    public static function toMinor(float|string $amount, string $currency): int
    {
        return (int) round(((float) $amount) * (10 ** self::exponent($currency)));
    }

    /** "12.50" style string with the right number of decimals (Mollie, PayPal). */
    public static function decimalString(float|string $amount, string $currency): string
    {
        return number_format((float) $amount, self::exponent($currency), '.', '');
    }
}
