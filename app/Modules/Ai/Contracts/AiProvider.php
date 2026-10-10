<?php

namespace App\Modules\Ai\Contracts;

use App\Modules\Ai\Exceptions\AiException;
use App\Modules\Ai\Support\AiResult;

interface AiProvider
{
    /** Stable code stored with usage rows: openai | anthropic | gemini. */
    public function code(): string;

    /**
     * Ask the model for a JSON answer, optionally about a picture.
     *
     * @param  array{mime: string, base64: string}|null  $image
     *
     * @throws AiException
     */
    public function complete(string $system, string $prompt, int $maxTokens, ?array $image = null): AiResult;
}
