<?php

namespace App\Modules\Ai\Providers;

use App\Modules\Ai\Exceptions\AiException;
use App\Modules\Ai\Support\AiResult;

class AnthropicProvider extends HttpProvider
{
    public function code(): string
    {
        return 'anthropic';
    }

    public function complete(string $system, string $prompt, int $maxTokens): AiResult
    {
        $response = $this->send(fn ($http) => $http->withHeaders(['x-api-key' => $this->apiKey, 'anthropic-version' => '2023-06-01'])->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]));

        $text = collect((array) $response->json('content'))->where('type', 'text')->pluck('text')->implode('');

        if ($text === '') {
            throw new AiException('bad_response', 'empty');
        }

        return new AiResult($text, (int) $response->json('usage.input_tokens'), (int) $response->json('usage.output_tokens'));
    }
}
