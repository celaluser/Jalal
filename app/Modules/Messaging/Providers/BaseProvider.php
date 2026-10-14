<?php

namespace App\Modules\Messaging\Providers;

use App\Modules\Messaging\Contracts\MessageProvider;
use App\Modules\Messaging\Exceptions\MessagingException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

abstract class BaseProvider implements MessageProvider
{
    protected function http(): PendingRequest
    {
        return Http::timeout(12)->acceptJson();
    }

    /** @throws MessagingException */
    protected function check(Response $response, string $action): void
    {
        if (! $response->successful()) {
            $detail = $response->json('message') ?? $response->json('error.message') ?? $response->json('error_text') ?? $response->json('errors.0.description');
            $detail = is_string($detail) ? ': '.Str::limit($detail, 160) : '';

            throw new MessagingException("{$this->name()}: {$action} failed (HTTP {$response->status()}){$detail}");
        }
    }

    protected function digits(string $to): string
    {
        return ltrim(preg_replace('/\D+/', '', $to), '0');
    }
}
