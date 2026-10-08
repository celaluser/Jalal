<?php

namespace App\Modules\Ai\Providers;

use App\Modules\Ai\Exceptions\AiException;
use App\Modules\Ai\Support\AiResult;

class GeminiProvider extends HttpProvider
{
    public function code(): string
    {
        return 'gemini';
    }

    public function complete(string $system, string $prompt, int $maxTokens, ?array $image = null): AiResult
    {
        // The key goes in a header, not the URL, so it cannot end up in access logs.
        $response = $this->send(fn ($http) => $http->withHeaders(['x-goog-api-key' => $this->apiKey])
            ->post('https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($this->model).':generateContent', [
                'systemInstruction' => ['parts' => [['text' => $system]]],
                'contents' => [['role' => 'user', 'parts' => array_merge($image ? [['inline_data' => ['mime_type' => $image['mime'], 'data' => $image['base64']]]] : [], [['text' => $prompt]])]],
                'generationConfig' => ['maxOutputTokens' => $maxTokens, 'responseMimeType' => 'application/json'],
            ]));

        $text = collect((array) $response->json('candidates.0.content.parts'))->pluck('text')->filter()->implode('');

        if ($text === '') {
            throw new AiException('bad_response', 'empty');
        }

        return new AiResult($text, (int) $response->json('usageMetadata.promptTokenCount'), (int) $response->json('usageMetadata.candidatesTokenCount'));
    }
}
