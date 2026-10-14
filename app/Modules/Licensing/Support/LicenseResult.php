<?php

namespace App\Modules\Licensing\Support;

/**
 * Outcome of a purchase code check.
 *
 * valid=false, reachable=true  : the code was rejected (block).
 * valid=false, reachable=false : the licence server could not be reached (do not block, mark unverified).
 */
final class LicenseResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly bool $reachable = true,
        public readonly ?string $buyer = null,
        public readonly ?string $message = null,
    ) {}

    public static function valid(?string $buyer = null): self
    {
        return new self(true, true, $buyer);
    }

    public static function invalid(string $message): self
    {
        return new self(false, true, null, $message);
    }

    public static function unreachable(string $message): self
    {
        return new self(false, false, null, $message);
    }
}
