<?php

namespace App\Modules\Ai\Support;

/** What a provider answered, in a form that is the same for every provider. */
final class AiResult
{
    public function __construct(
        public readonly string $text,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
    ) {}
}
