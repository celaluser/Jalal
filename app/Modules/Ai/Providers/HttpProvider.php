<?php

namespace App\Modules\Ai\Providers;

use App\Modules\Ai\Contracts\AiProvider;
use App\Modules\Ai\Exceptions\AiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** Shared HTTP behaviour: one retry on overload, short timeouts, and error mapping that never leaks keys. */
abstract class HttpProvider implements AiProvider
{
    public function __construct(protected readonly string $apiKey, protected readonly string $model) {}

    protected function http(): PendingRequest
    {
        // 429 and 5xx get one more try; the user is waiting, so no long back-off.
        return Http::timeout(60)->connectTimeout(10)->acceptJson()->asJson()
            ->retry(2, 800, fn ($e) => $e instanceof ConnectionException || ($e->response?->status() === 429) || ($e->response?->serverError() ?? false), throw: false);
    }

    /** @throws AiException */
    protected function send(callable $call): Response
    {
        try {
            $response = $call($this->http());
        } catch (ConnectionException $e) {
            throw new AiException('provider_error', 'connection');
        }

        if ($response->status() === 429) {
            throw new AiException('rate_limited');
        }

        if ($response->failed()) {
            // The status and a short provider message help the admin; the request (which holds the key) is never included.
            throw new AiException('provider_error', $response->status().' '.mb_substr((string) ($response->json('error.message') ?? $response->json('error') ?? ''), 0, 200));
        }

        return $response;
    }
}
