<?php

namespace App\Modules\Ai\Providers;

use App\Modules\Ai\Exceptions\AiException;
use App\Modules\Ai\Support\AiResult;

class OpenAiProvider extends HttpProvider
{
    public function code(): string
    {
        return 'openai';
    }

    public function complete(string $system, string $prompt, int $maxTokens, ?array $image = null): AiResult
    {
        $response = $this->send(fn ($http) => $http->withToken($this->apiKey)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model,
            'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $image ? [['type' => 'text', 'text' => $prompt], ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$image['mime'].';base64,'.$image['base64']]]] : $prompt]],
            'max_completion_tokens' => $maxTokens,
            'response_format' => ['type' => 'json_object'],
        ]));

        $text = $response->json('choices.0.message.content');

        if (! is_string($text) || $text === '') {
            throw new AiException('bad_response', 'empty');
        }

        return new AiResult($text, (int) $response->json('usage.prompt_tokens'), (int) $response->json('usage.completion_tokens'));
    }
}
