<?php

namespace App\Modules\Ai\Exceptions;

use RuntimeException;

/** Reasons are stable codes translated as ai.error_{reason}: not_configured, no_credits, provider_error, rate_limited, bad_response. */
class AiException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $detail = '')
    {
        parent::__construct($detail !== '' ? "{$reason}: {$detail}" : $reason);
    }
}
