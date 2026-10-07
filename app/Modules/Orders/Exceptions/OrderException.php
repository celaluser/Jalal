<?php

namespace App\Modules\Orders\Exceptions;

use RuntimeException;

/** A guest's order cannot be accepted. The code is stable and translated by the caller: orders.error_{code}. */
class OrderException extends RuntimeException
{
    /**
     * @param  array<int, mixed>  $details  e.g. the cart lines that failed pricing
     */
    public function __construct(public readonly string $reason, public readonly array $details = [])
    {
        parent::__construct($reason);
    }
}
